<?php
/**
 * BA-IDENTITY-01G dormant WordPress REST adapter.
 * Requires explicit feature flag and dependency wiring by reviewed Core.
 * Existing Contribution Service and Publication Service are unaffected.
 * No hooks registered merely by including this file.
 */
declare(strict_types=1);
require_once __DIR__.'/community-gateway.php';

final class BadAround_Identity_Community_Rest_Adapter {
    public const REST_NAMESPACE = 'badaround/v1';
    public const COOKIE_NAME = 'ba_identity_session';
    public const MAX_BODY_BYTES = 8192;

    public function __construct(private BadAround_Identity_Community_Gateway $gateway) {}

    public function register_hooks(): bool {
        if (!defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            || BADAROUND_IDENTITY_COMMUNITY_ENABLED !== true
            || !function_exists('add_action')) {
            return false;
        }
        add_action('rest_api_init',[$this,'register_routes']);
        return true;
    }

    public function register_routes(): void {
        if (!defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            || BADAROUND_IDENTITY_COMMUNITY_ENABLED !== true
            || !function_exists('register_rest_route')) {
            return;
        }
        foreach (['/community-comments'=>'comment','/community-revisions'=>'revision'] as $route=>$kind) {
            register_rest_route(self::REST_NAMESPACE,$route,[
                'methods'=>'POST',
                'callback'=>function($request) use($kind) { return $this->submit($request,$kind); },
                'permission_callback'=>'__return_true',
            ]);
        }
        // Moderator route is intentionally NOT exposed yet. Requires a
        // separate audited WordPress/admin authorization transaction.
    }

    /** All request-to-domain calls are fail closed with generic responses. */
    public function submit($request,string $kind) {
        if (!defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED')
            || BADAROUND_IDENTITY_COMMUNITY_ENABLED !== true
            || !in_array($kind,['comment','revision'],true)
            || !is_object($request)
            || !method_exists($request,'get_header')
            || !method_exists($request,'get_json_params')
            || !method_exists($request,'get_body')) {
            return $this->error(403);
        }
        $contentType=strtolower(trim(explode(';',(string)$request->get_header('content-type'),2)[0]));
        $body=(string)$request->get_body();
        if ($contentType!=='application/json' || strlen($body)>self::MAX_BODY_BYTES || $body==='') {
            return $this->error(400);
        }
        $payload=$request->get_json_params();
        if (!is_array($payload)) { return $this->error(400); }
        $token=$_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) {
            return $this->error(403);
        }
        $csrf=$request->get_header('x-badaround-csrf');
        $origin=$request->get_header('origin');
        if (!is_string($csrf) || !is_string($origin)) { return $this->error(403); }
        $now=time();
        try {
            if ($kind==='comment') {
                $event=$payload['event_id'] ?? null;
                $text=$payload['body'] ?? null;
                $mode=$payload['display_mode'] ?? null;
                // Parent lookup and reply checks must be wired to trusted
                // server storage. Until then reject replies, never trust
                // parent metadata from JSON.
                if (array_key_exists('parent_id',$payload)
                    || !is_int($event) || !is_string($text) || !is_string($mode)) {
                    return $this->error(400);
                }
                $ok=$this->gateway->submit_comment($token,$csrf,$origin,
                    $event,$text,$mode,null,$now);
            } else {
                // Server-loaded canonical snapshot is not wired. Do not take
                // a user-supplied snapshot digest as trusted evidence.
                return $this->error(503);
            }
            return $ok ? $this->receipt() : $this->error(403);
        } catch(Throwable $error) {
            return $this->error(503);
        }
    }

    private function receipt() {
        return function_exists('rest_ensure_response')
            ? rest_ensure_response(['ok'=>true,'status'=>'pending_moderation'])
            : ['ok'=>true,'status'=>'pending_moderation'];
    }
    private function error(int $status) {
        if (class_exists('WP_Error')) {
            return new WP_Error('ba_identity_unavailable','Operazione non disponibile.',
                ['status'=>$status]);
        }
        return ['ok'=>false,'status'=>$status];
    }
}
