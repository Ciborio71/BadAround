<?php
require __DIR__.'/quick-draft-repository.php';
class QuickFixtureStore {
 public array $rows=[];
 public function insert($r){$this->rows[$r['id']]=$r;return true;}
 public function find($id){return $this->rows[$id]??null;}
 public function compareAndSwap($id,$rev,$next){if($this->rows[$id]['revision']!==$rev)return false;$this->rows[$id']=$next;return true;}
}
$db=new QuickFixtureStore();
$repo=new BadAround_Quick_Draft_Repository($db);
$now=new DateTimeImmutable('2026-10-29');
$draft=$repo->create('Veicoli','test@example.invalid',$now);
if(!$repo->update($draft['id'],$draft['token'],1,['description'=>'Episodio segnalato'],$now))throw new RuntimeException('Update failed');
if($repo->update($draft['id'],$draft['token'],1,['description'=>'Old revision'],$now))throw new RuntimeException('Revision guard failed');
if($repo->update($draft['id'],$draft['token'],2,['publication_status'=>'publish'],$now))throw new RuntimeException('Field guard failed');
if($repo->update($draft['id'],str_repeat('0',64),2,['description'=>'Invalid token'],$now))throw new RuntimeException('Token guard failed');
if($repo->update($draft['id'],$draft['token'],2,['description'=>'Expired'],$now->modify('+2 days')))throw new RuntimeException('Expiry guard failed');
echo 'QUICK-02B PASS'.PHP_EOL;
