<?php
declare(strict_types=1);
require_once __DIR__.'/quick-draft-security.php';
/** QUICK-02B: storage abstraction; no database hooks or public routes. */
final class BadAround_Quick_Draft_Repository {
 private $store;
 public function __construct($store) {$this->store=$store;}
 public function create(string $category,string $email,DateTimeImmutable $now):array {
  if(trim($category)==='' || !filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Missing required values');
  $token=BadAround_Quick_Draft_Security::newToken();
  $id=bin2hex(random_bytes(16));
  $record=['id'=>$id,'state'=>'private_draft','revoked'=>false,'revision'=>1,
   'expires_at'=>$now->getTimestamp()+86400,
   'token_hash'=>BadAround_Quick_Draft_Security::digest($token),
   'email'=>$email,'payload'=>['category'=>$category]];
  if(!$this->store->insert($record))throw new RuntimeException('Storage rejected draft');
  return ['id'=>$id,'token'=>$token];
 }
 public function update(string $id,string $token,int $revision,array $data,DateTimeImmutable $now):bool {
  $record=$this->store->find($id);
  if(!is_array($record) || !BadAround_Quick_Draft_Security::authorize($token,$record,$now) || $record['revision']!==$revision)return false;
  $allowed=['category','event_subtype','description','place','incident_date'];
  if(!$data||array_diff(array_keys($data),$allowed))return false;
  foreach($data as $key=>$value) {
   if(!is_string($value)||strlen($value)>1500)return false;
   $record['payload'][$key]=$value;
  }
  $record['revision']++;
  return $this->store->compareAndSwap($id,$revision,$record)===true;
 }
}
