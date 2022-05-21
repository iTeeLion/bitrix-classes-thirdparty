function AmoApi()
{
    $params = Array(
        'api' => Array(
            'subdomain' => '',
            'userLogin' => '',
            'userHash' => '',
            'amoCreatedByUserId' => 123123,
            'amoResponsibleUserId' => 123123,
            'amoGoodsCatalogID' => 1,
        ),
        'userGroupsFieldId' => 251507,
        'userGroupsToSync' => Array(8,9,10),
        'contactCustomFields' => Array(
            173201 => Array('valueByCode' => 'ID'),
            148335 => Array('valueByCode' => 'EMAIL', 'enum' => 'PRIV'),
        ),
        'goodCustomFields' => Array(
            256541 => Array('valueByCode' => 'PRICE'),
        ),
        'dealAmoIdPropId' => 10,
        'dealCustomFields' => Array(
            230319 => Array('valueByCode' => 'ID'),
            344949 => Array(
                'valueByCodeList' => 'PAY_SYSTEM_ID',
                'list' => Array(10 => 'Карта Сбербанка', 11 => 'Внутренний счёт', 12 => 'Онлайн-оплата картой'),
            ),
            344951 => Array(
                'valueByCodeList' => 'DELIVERY_ID',
                'list' => Array(3 => 'Почта России (Доставка зарубеж)', 4 => 'Почта России', 5 => 'EMS', 1 => 'СДЕК'),
            ),
        ),
        'dealStatuses' => Array(
            'L' => 22069600, // Корзина не заказана
            'N' => 22069603, // Принят
            'P' => 22069606, // Оплачен
            'F' => 142, // Выполнен
            'CANCELED' => 143,
        ),
    );

    $AmoApi = new \Prominado\AmoCRM\Bitrix($params);
    return $AmoApi;
}