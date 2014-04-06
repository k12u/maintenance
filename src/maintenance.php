<?php
define("MAINTENANCE", 'maintenance');
define("UNMAINTENANCE", 'unmaintenance');

require __DIR__ . "/../vendor/autoload.php";

$appName = 'kawamoto';
$maintenance_tag = 'kawamoto_test_maintenance';
$production_tag = 'kawamoto_test_prodction';
$loadBalancerName = 'kawamoto';
$time_to_wait = 15;

use Aws\Common\Aws;

echo "This script set app maintenance. Proceed? (Set (M)aintenance/(U)nset maintenance/e(X)it) ";
$line = trim(fgets(STDIN)); // reads one line from STDIN
if (preg_match('/^m/i', $line)) {
    $mode = MAINTENANCE;
} else if (preg_match('/^u/i', $line)) {
    $mode = UNMAINTENANCE;
} else {
    exit();
}

if ($mode === MAINTENANCE) {
    $tag_to_register = $maintenance_tag;
    $tag_to_unregister = $production_tag;
} else {
    $tag_to_register = $production_tag;
    $tag_to_unregister = $maintenance_tag;
}

$aws = Aws::factory('src/config.php');

$elb = $aws->get('ElasticLoadBalancing');
$ec2 = $aws->get('Ec2');

$lb = $elb->describeInstanceHealth(array(
    'LoadBalancerName' => $loadBalancerName,
));

printf("Current ELB Status: \n");
foreach ($lb['InstanceStates'] as $instance) {
    printf("%s\t%s\n", $instance['InstanceId'], $instance['State']);
}

$app_instances = $ec2->describeInstances(array(
        'DryRun' => false,
        'Filters' => array(
            array(
                'Name' => 'tag:appName', 'Values' => array($appName),
            ),
        )
));

$instances = array();
$instances_to_register = array();
$instances_to_unregister = array();
$instance_ids_to_register = array();
$instance_ids_to_unregister = array();

foreach ($app_instances['Reservations'] as $reservation) {
    foreach ($reservation['Instances'] as $instance) {
        foreach ($instance['Tags'] as $tag) {
            if ($tag['Key'] === 'instanceType' && $tag['Value'] === $tag_to_register) {
                if($instance['State']['Name'] === 'running') {
                    $instances_to_register[] = $instance;
                }
            }
            if ($tag['Key'] === 'instanceType' && $tag['Value'] === $tag_to_unregister) {
                $instances_to_unregister[] = $instance;
            }
        }
    }
}

foreach ($instances_to_unregister as $instance) {
    $instance_ids_to_unregister[] = array('InstanceId' => $instance['InstanceId']);
}
foreach ($instances_to_register as $instance) {
    $instance_ids_to_register[] = array('InstanceId' => $instance['InstanceId']);
}

printf("\nList of registering instances: \n");
printf(implode("\n", array_map(function($i){ return $i['InstanceId'];}, $instance_ids_to_register)));

printf("\nList of unregistering instances: \n");
printf(implode("\n", array_map(function($i){ return $i['InstanceId'];}, $instance_ids_to_unregister)));

printf("\n\n=== Committing changes ===\n");


printf("\nRegistering instances\n");
//elb にメンテインスタンスを追加
$null = $elb->registerInstancesWithLoadBalancer(array(
    'LoadBalancerName' => $loadBalancerName,
    'Instances' => $instance_ids_to_register,
));

printf("\nWaiting until registered instances are ready... for *%d* seconds\n", $time_to_wait);
sleep($time_to_wait);

printf("\nUnregistering instances\n");
//elb からProductionインスタンスを削除
$null = $elb->deregisterInstancesFromLoadBalancer(array(
    'LoadBalancerName' => $loadBalancerName,
    'Instances' => $instance_ids_to_unregister,
));

$lb = $elb->describeInstanceHealth(array(
    'LoadBalancerName' => $loadBalancerName,
));

printf("\nCurrent ELB Status: \n");
foreach ($lb['InstanceStates'] as $instance) {
    printf("%s\t%s\n", $instance['InstanceId'], $instance['State']);
}
