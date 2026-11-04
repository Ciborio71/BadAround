<?php
/**
 * BA-IDENTITY-01R: fail-closed privacy notice binding.
 * Only trusted WordPress post data may define the notice version.
 * No live hooks or routes registered by including this module.
 */
declare(strict_types=1);
final class BadAround_Identity_WordPress_Privacy_Notice {
    public const PAGE_ID=3;
    public const EXPECTED_MODIFIED_GMT='2026-10-24 16:26:03';
    public const VERSION='privacy-20261024T162603Z';

    public static function verified_version():?string {
        if (!function_exists('get_post')) { return null; }
        try {
            $page=get_post(self::PAGE_ID);
            if (!is_object($page)
                || (int)($page->ID??0)!==self::PAGE_ID
                || ($page->post_type??null)!=='page'
                || ($page->post_status??null)!=='publish'
                || ($page->post_name??null)!=='privacy-policy'
                || ($page->post_modified_gmt??null)!==self::EXPECTED_MODIFIED_GMT
                || !is_string($page->post_content??null)
                || trim($page->post_content)==='') {
                return null;
            }
            // A published post can still be an editorial draft. The live
            // notice currently contains an explicit pre-publication warning.
            // Never accept it as a reviewed user-facing privacy statement.
            $plain=mb_strtolower(html_entity_decode(strip_tags(
                $page->post_content
            ),ENT_QUOTES|ENT_HTML5,'UTF-8'),'UTF-8');
            foreach ([
                'bozza pre-pubblicazione',
                'da sottoporre a revisione',
                'devono essere completati',
                'versione definitiva dell’informativa'
            ] as $marker) {
                if (str_contains($plain,$marker)) { return null; }
            }
            return self::VERSION;
        } catch(Throwable $error) { return null; }
    }
}
