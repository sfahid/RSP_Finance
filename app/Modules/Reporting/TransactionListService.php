<?php
declare(strict_types=1);
namespace ReadyFinance;
final class TransactionListService {
 public function __construct(private Core $c){}
 public function view(int $h,array $p):array {
  $c=$this->c;$q=trim((string)($p['search']??''));if(mb_strlen($q)>120)throw new Fault('Use at most 120 search characters.');$filter=$p['filter']??'all';if(!in_array($filter,['all','income','expense','review'],true))throw new Fault('Choose a transaction filter.');$page=max(1,(int)($p['page']??1));$history=($p['history']??'0')==='1';
  $review="EXISTS(SELECT 1 FROM journal_entries j JOIN journal_lines l ON l.journal_id=j.id AND l.household_id=j.household_id JOIN ledger_accounts a ON a.id=l.ledger_id AND a.household_id=l.household_id WHERE j.transaction_id=t.id AND j.household_id=t.household_id AND a.system_key IN ('uncategorized_income','uncategorized_expense'))";
  $where='t.household_id=? AND LOCATE(LOWER(?),LOWER(t.description))>0';$args=[$h,$q];if(!$history||$filter==='review')$where.=" AND t.status='posted' AND t.type<>'reversal'";
  if($filter==='review')$where.=' AND '.$review;
  if(in_array($filter,['income','expense'],true)){$where.=" AND EXISTS(SELECT 1 FROM journal_entries j JOIN journal_lines l ON l.journal_id=j.id AND l.household_id=j.household_id JOIN ledger_accounts a ON a.id=l.ledger_id AND a.household_id=l.household_id WHERE j.transaction_id=t.id AND j.household_id=t.household_id AND a.class=?)";$args[]=$filter;}
  foreach(['from'=>'>=','to'=>'<='] as $key=>$op)if(!empty($p[$key])){$where.=' AND t.effective_date'.$op.'?';$args[]=Core::date($p[$key]);}if(!empty($p['from'])&&!empty($p['to'])&&$p['from']>$p['to'])throw new Fault('Start date must precede end date.');
  $count=(int)$c->query('SELECT COUNT(*) FROM transactions t WHERE '.$where,$args)->fetchColumn();$pages=max(1,(int)ceil($count/50));$page=min($page,$pages);$editing=(new AccountManagementService($c))->ready();
  $rows=$c->all('SELECT t.uuid,t.type,t.description,t.effective_date,t.amount,t.status,t.row_version,'.($editing?'t.replaces_id':'NULL replaces_id').','.$review.' needs_review FROM transactions t WHERE '.$where.' ORDER BY t.effective_date DESC,t.id DESC LIMIT 50 OFFSET '.(($page-1)*50),$args);
  return ['rows'=>(new PartyService($c))->labelRows($h,$rows),'page'=>$page,'pages'=>$pages,'count'=>$count,'filter'=>$filter,'search'=>$q,'history'=>$history];
 }
}
