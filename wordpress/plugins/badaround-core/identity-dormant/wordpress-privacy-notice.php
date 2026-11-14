<?php
/**
 * BA-IDENTITY-01R: fail-closed privacy notice binding.
 * Only trusted WordPress post data may define the notice version.
 * No live hooks or routes registered by including this module.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-private-access.php';
final class BadAround_Identity_WordPress_Privacy_Notice {
    public const PAGE_ID=3;
    public const EXPECTED_MODIFIED_GMT='2026-10-24 16:26:03';
    public const VERSION='privacy-20261024T162603Z';

    /** An adopted QA notice is independent of the public/legal release gate. */
    public static function qa_mode():bool {
        return defined('BADAROUND_IDENTITY_QA_NOTICE_ENABLED')
            && BADAROUND_IDENTITY_QA_NOTICE_ENABLED===true;
    }

    public static function runtime_version():?string {
        if (self::qa_mode())return self::qa_record()['version']??null;
        return self::verified_version();
    }

    /** Server-managed adoption only; never accepts request data or publishes a page. */
    public static function qa_record():?array {
        if (!self::qa_mode()
            || !defined('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED')
            || BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED!==true
            || !BadAround_Identity_Private_Access::authorized()
            || !function_exists('get_option') || !function_exists('get_post')
            || !function_exists('home_url')
            || rtrim((string)home_url('/'),'/')!=='https://badaround.it')return null;
        try {
            $record=get_option('badaround_identity_qa_notice_adoption',null);
            if (!is_array($record) || ($record['scope']??null)!=='private_identity_qa'
                || ($record['state']??null)!=='owner_adopted'
                || !is_bool($record['legal_review_verified']??null)
                || ($record['approval_source']??null)!=='explicit_user_approval_in_conversation'
                || !is_int($record['page_id']??null) || $record['page_id']<=0
                || !is_string($record['sha256']??null)
                || !preg_match('/^[a-f0-9]{64}$/D',$record['sha256'])
                || ($record['page_slug']??null)!=='identity-qa-informativa'
                || ($record['version']??null)!=='qa-privacy-'.substr($record['sha256'],0,16)
                || !is_string($record['adopted_at_gmt']??null)
                || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D',$record['adopted_at_gmt']))return null;
            $page=get_post($record['page_id']);
            if (!is_object($page) || (int)($page->ID??0)!==$record['page_id']
                || ($page->post_type??null)!=='page' || ($page->post_status??null)!=='private'
                || ($page->post_name??null)!==$record['page_slug']
                || !is_string($page->post_content??null) || trim($page->post_content)===''
                || strlen($page->post_content)>100000
                || !hash_equals($record['sha256'],hash('sha256',$page->post_content)))return null;
            // A hash change always requires a new explicit adoption. A QA
            // adoption never modifies verified_version(), which stays public.
            return ['version'=>$record['version'],'content'=>$page->post_content,
                'page_id'=>$record['page_id'],'scope'=>'private_identity_qa',
                'legal_review_verified'=>$record['legal_review_verified']];
        } catch(Throwable $error) {return null;}
    }

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
