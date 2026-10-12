<?php
declare(strict_types=1);
if(is_file(__DIR__.'/vendor/autoload.php'))require_once __DIR__.'/vendor/autoload.php';
spl_autoload_register(static function(string $class):void {
 $map=[
  'ReadyFinance\\AccountManagementService'=>'app/Modules/Money/AccountManagementService.php',
  'ReadyFinance\\TransactionEditingService'=>'app/Modules/Accounting/TransactionEditingService.php',
  'ReadyFinance\\StatementReader'=>'app/Modules/Import/StatementReader.php',
  'ReadyFinance\\ImportService'=>'app/Modules/Import/ImportService.php',
  'ReadyFinance\\Core'=>'app/Shared/Core.php',
  'ReadyFinance\\Fault'=>'app/Shared/Core.php',
  'ReadyFinance\\ChartService'=>'app/Modules/Accounting/ChartService.php',
  'ReadyFinance\\Accounting'=>'app/Modules/Accounting/Accounting.php',
  'ReadyFinance\\Finance'=>'app/Application/Finance.php',
  'ReadyFinance\\Http'=>'app/Http/Http.php',
  'ReadyFinance\\HouseholdService'=>'app/Modules/Household/HouseholdService.php',
  'ReadyFinance\\ReportingService'=>'app/Modules/Reporting/ReportingService.php',
  'ReadyFinance\\AccountService'=>'app/Modules/Money/AccountService.php',
  'ReadyFinance\\BillService'=>'app/Modules/Bills/BillService.php',
  'ReadyFinance\\BudgetService'=>'app/Modules/Budgets/BudgetService.php',
  'ReadyFinance\\FundService'=>'app/Modules/Funds/FundService.php',
  'ReadyFinance\\MemberService'=>'app/Modules/Household/MemberService.php',
  'ReadyFinance\\CategoryService'=>'app/Modules/Classification/CategoryService.php',
  'ReadyFinance\\ObligationService'=>'app/Modules/Obligations/ObligationService.php',
  'ReadyFinance\\RecurrenceService'=>'app/Modules/Recurrence/RecurrenceService.php'
 ];
 if(isset($map[$class]))require_once __DIR__.'/'.$map[$class];
});
