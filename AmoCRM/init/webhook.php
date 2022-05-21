<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$params = Array(
    'token' => '',
    'subdomain' => '',
    'amoApiUserId' => 123123,
    'userGroupsFieldId' => 123123,
    'userGroupsToSync' => Array(487485 => 8, 487487 => 9, 487489 => 10),
    'contactCustomFields' => Array(
        173201 => Array('valueByCode' => 'ID'),
        148335 => Array('valueByCode' => 'EMAIL', 'enumAmoId' => 291341),
    ),
    'dealCustomFields' => Array(
        230319 => Array('valueByCode' => 'ID'),
        344949 => Array(
            'valueByCodeList' => 'PAY_SYSTEM_ID',
            'list' => Array(12 => 633505, 10 => 633507, 11 => 633509),
        ),
        344951 => Array(
            'valueByCodeList' => 'DELIVERY_ID',
            'list' => Array(3 => 633511, 4 => 633513, 5 => 633515, 1 => 634091),
        ),
    ),
    'dealStatuses' => Array(
        22069603 => 'N', // Принят
        22069606 => 'P', // Оплачен
        142 => 'F', // Выполнен
    ),
);

$WH = new \Prominado\AmoCRM\WebHook($params);
$res = $WH->init($_REQUEST);