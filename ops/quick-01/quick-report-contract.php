<?php
/**
 * QUICK-01 — Pure state transition contract for private quick-report drafts.
 * No WordPress writes, no publication, no email dispatch.
 */
declare(strict_types=1);
final class BadAround_Quick_Report_Contract {
 public const VERSION='badaround-quick-draft/v0.1';
 private const STATES=['local','private_draft','ready_for_intake','submitted_for_moderation'];
 public static function transition(string $state,string $action,array $context):array {
  if(!in_array($state,self::STATES,true))throw new InvalidArgumentException('Invalid state');
  if($action==='save_draft'&&$state==='local') {
   if(empty($context['category'])||!is_string($context['category']))
    return ['allowed'=>false,'reason'=>'category_required'];
   if(empty($context['user_initiated'])||$context['user_initiated']!==true)
    return ['allowed'=>false,'reason'=>'explicit_user_action_required'];
   return ['allowed'=>true,'next'=>'private_draft','publication'=>false,'radar_inclusion'=>false];
  }
  if($action==='mark_ready'&&$state==='private_draft') {
   foreach(['category','event_subtype','place_confirmed','minimum_description','reporter_contact_verified'] as $key) {
    if(empty($context[$key]))return ['allowed'=>false,'reason'=>$key.'_required'];
   }
   return ['allowed'=>true,'next'=>'ready_for_intake','publication'=>false,'radar_inclusion'=>false];
  }
  if($action==='submit'&&$state==='ready_for_intake'){
   if(($context['canonical_intake_validated']??false)!==true)
    return ['allowed'=>false,'reason'=>'canonical_intake_required'];
   return ['allowed'=>true,'next'=>'submitted_for_moderation','publication'=>false,'radar_inclusion'=>false];
  }
  return ['allowed'=>false,'reason'=>'transition_not_allowed'];
 }
}
