<?php
/**
 * QUICK-02A — pure private-draft token and lifecycle policy.
 * No persistence, HTTP route, sending email, or production bootstrap.
 */
declare(strict_types=1);
final class BadAround_Quick_Draft_Security {
 public const TOKEN_BYTES=32;
 public const TTL_SECONDS=86400;
 public static function newToken():string {
  return bin2hex(random_bytes(self::TOKEN_BYTES));
 }
 public static function digest(string $token):string {
  if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new InvalidArgumentException('Invalid token format');
  return hash('sha256',$token);
 }
 public static function authorize(string $provided, array $record, DateTimeImmutable $now):bool {
  if(($record['state']??null)!=='private_draft')return false;
  if(($record['revoked']??true)!==false)return false;
  if(!is_string($record['token_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$record['token_hash']))return false;
  if(!is_int($record['expires_at']??null)||$record['expires_at']<=$now->getTimestamp())return false;
  try{$hash=self::digest($provided);}catch(InvalidArgumentException $e){return false;}
  return hash_equals($record['token_hash'],$hash);
 }
 public static function safePublicView(array $record):array {
  // Never echo contact email, precise location, original media, token or private payload.
  return ['status'=>'private_draft','completable'=>true];
 }
}
