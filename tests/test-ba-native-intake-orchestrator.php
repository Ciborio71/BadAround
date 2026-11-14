<?php
declare(strict_types=1);
require_once __DIR__ . '/../wordpress/plugins/badaround-core/identity-dormant/native-intake-orchestrator.php';

final class OrchestratorReceipt implements BadAround_Identity_Intake_Receipt {
    public bool $valid = true;
    public function is_new_canonical_report(int $reportId, int $eventId): bool {
        return $this->valid && (($reportId === 17 && $eventId === 27) || ($reportId === 18 && $eventId === 28));
    }
}
final class OrchestratorGrant implements BadAround_Identity_Ownership_Grant {
    public int $grants = 0;
    public bool $success = true;
    private array $written = [];
    public function grant_creator(string $identityId, string $kind, int $resourceId, int $now): bool {
        ++$this->grants;
        if (!$this->success || $kind !== 'report' || isset($this->written[$resourceId])) return false;
        $this->written[$resourceId] = true;
        return true;
    }
}
final class OrchestratorJournal implements BadAround_Identity_Recovery_Journal {
    public array $intents = [];
    public bool $allowInsert = true;
    public function create(array $intent): bool {
        if (!$this->allowInsert || isset($this->intents[$intent['operation_id']])) return false;
        $this->intents[$intent['operation_id']] = $intent;
        return true;
    }
    public function load(string $operationId): ?array {
        return $this->intents[$operationId] ?? null;
    }
    public function transition(string $operationId,int $revision,string $outcome,int $now): bool {
        if (!isset($this->intents[$operationId]) || $revision !== 0 || $outcome !== 'granted') return false;
        $this->intents[$operationId]['state'] = 'bound';
        return true;
    }
}
function assertOrchestrator(bool $pass,string $name):void {
    if (!$pass) throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$store = new BadAround_Identity_Session_Memory_Store();
$sessions = new BadAround_Identity_Sessions($store, str_repeat('K',32));
$identity = 'identity_test_1234';
$token = $sessions->issue($identity,1000);
$status = new BadAround_Identity_Active_Memory_Status();
$status->active[$identity] = true;
$receipts = new OrchestratorReceipt();
$grants = new OrchestratorGrant();
$journal = new OrchestratorJournal();
$flow = new BadAround_Identity_Native_Intake_Orchestrator(
    new BadAround_Identity_Native_Intake_Gate($sessions,$status),
    new BadAround_Identity_Durable_Ownership_Handoff($sessions,$status,$receipts,$grants,$journal)
);
$submitCalls = 0;
$success = static function() use (&$submitCalls):array {
    ++$submitCalls;
    return ['status'=>'success','report_id'=>17,'event_id'=>27,'duplicate'=>false,
        'persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>false]];
};
assertOrchestrator($flow->submit(null,$success,1010)['state']==='denied','anonymous denied before submit');
assertOrchestrator($flow->submit('bogus',$success,1010)['state']==='denied','invalid cookie denied before submit');
assertOrchestrator($submitCalls===0,'unauthorized flows did not persist anything');
$regular=$flow->submit($token,$success,1011);
assertOrchestrator($regular['state']==='owner_confirmed' && $regular['event_id']===27,
    'regular canonical success confirms owner');
$media = static function() use (&$submitCalls):array {
    ++$submitCalls;
    return ['status'=>'success','report_id'=>18,'event_id'=>28,'duplicate'=>false,
        'persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>false]];
};
$mediaResult=$flow->submit($token,$media,1011);
assertOrchestrator($mediaResult['state']==='owner_confirmed' && $mediaResult['event_id']===28,
    'media canonical success confirms separate owner');
assertOrchestrator($submitCalls===2 && $grants->grants===2,'both transports traverse same coordinator');
$journal->allowInsert=false;
assertOrchestrator($flow->submit($token,$success,1012)['state']==='owner_pending','journal outage never confirms ownership');
$journal->allowInsert=true;
$duplicate = static function():array {
    return ['status'=>'success','report_id'=>17,'event_id'=>27,'duplicate'=>true,
        'persistence'=>['completed'=>true,'duplicate'=>true,'recovered'=>true]];
};
$before=$grants->grants;
assertOrchestrator($flow->submit($token,$duplicate,1013)['state']==='duplicate','recovered or duplicate report never assigns owner');
assertOrchestrator($grants->grants===$before,'duplicate did not grant');
assertOrchestrator($flow->submit($token,static fn():array=>[
    'status'=>'success','report_id'=>19,'event_id'=>29,'duplicate'=>false
],1013)['state']==='owner_pending',
    'missing persistence metadata remains ambiguous, never duplicate or confirmed');
assertOrchestrator($grants->grants===$before,
    'ambiguous receipt cannot grant');

assertOrchestrator($flow->submit($token,static fn():array=>['status'=>'error'],1013)['state']==='submission_failed','failed canonical intake never grants');
assertOrchestrator($flow->submit($token,static function(){throw new RuntimeException('simulated');},1013)['state']==='submission_failed','exception does not disclose internal details');
$status->active[$identity]=false;
$before=$submitCalls;
assertOrchestrator($flow->submit($token,$success,1014)['state']==='denied','revocation denied before persistence');
assertOrchestrator($submitCalls===$before,'revoked identity cannot invoke pipeline');
echo 'ALL_NATIVE_INTAKE_ORCHESTRATOR_TESTS_PASS'.PHP_EOL;
