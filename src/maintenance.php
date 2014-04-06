<?php
require __DIR__ . "/../vendor/autoload.php";

$appName = 'kawamoto';
$maintenance_tag = 'kawamoto_test_maintenance';
$production_tag = 'kawamoto_test_prodction';
$loadBalancerName = 'kawamoto';

use Aws\Common\Aws;

echo "This script set app maintenance. Proceed? (Set (M)aintenance/(U)nset maintenance/e(X)it) ";
$line = trim(fgets(STDIN)); // reads one line from STDIN
if (strpos($line, 'y') !== 0) {
    exit();
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
$maintenance_instances = array();
$production_instances = array();
$maintenance_instance_ids = array();
$production_instance_ids = array();

foreach ($app_instances['Reservations'] as $reservation) {
    foreach ($reservation['Instances'] as $instance) {
        foreach ($instance['Tags'] as $tag) {
            if ($tag['Key'] === 'instanceType' && $tag['Value'] === $maintenance_tag) {
                if($instance['State']['Name'] === 'running') {
                    $maintenance_instances[] = $instance;
                }
            }
            if ($tag['Key'] === 'instanceType' && $tag['Value'] === $production_tag) {
                $production_instances[] = $instance;
            }
        }
    }
}

foreach ($production_instances as $instance) {
    $production_instance_ids[] = array('InstanceId' => $instance['InstanceId']);
}
foreach ($maintenance_instances as $instance) {
    $maintenance_instance_ids[] = array('InstanceId' => $instance['InstanceId']);
}

printf("\nList of maintenance instances: \n");
printf(implode("\n", array_map(function($i){ return $i['InstanceId'];}, $maintenance_instance_ids)));

printf("\nList of production instances: \n");
printf(implode("\n", array_map(function($i){ return $i['InstanceId'];}, $production_instance_ids)));

printf("\n\n=== Adding maintenance instances to LB ===\n");


//elb にメンテインスタンスを追加
$null = $elb->registerInstancesWithLoadBalancer(array(
    'LoadBalancerName' => $loadBalancerName,
    'Instances' => $maintenance_instance_ids,
));

//elb からProductionインスタンスを削除
$null = $elb->deregisterInstancesFromLoadBalancer(array(
    'LoadBalancerName' => $loadBalancerName,
    'Instances' => $production_instance_ids,
));

$lb = $elb->describeInstanceHealth(array(
    'LoadBalancerName' => $loadBalancerName,
));

printf("\nCurrent ELB Status: \n");
foreach ($lb['InstanceStates'] as $instance) {
    printf("%s\t%s\n", $instance['InstanceId'], $instance['State']);
}
