<?php

namespace Prominado\AmoCRM;


class Bitrix
{

    protected $amoApi;
    protected $userAmoIdUfCode = 'UF_AMOCRM_ID';
    protected $userGroupsToSync = Array();
    protected $userGroupsFieldId;
    protected $contactCustomFields = Array();
    protected $goodAmoIdPropCode = 'AMOCRM_ID';
    protected $goodCustomFields = Array();
    protected $dealAmoIdPropId;
    protected $dealCustomFields = Array();
    protected $dealStatuses = Array();
    protected $logEvents = false;
    protected $logErrors = true;

    /**
     * Bitrix constructor.
     *
     * @param $params
     * @throws \Exception
     */
    public function __construct($params)
    {
        $this->amoApi = new Api($params['api']);
        $this->amoApi->auth();
        sleep(1);
        $this->setConfig($params);
    }

    /**
     * Конфигурация
     *
     * @param $params
     */
    public function setConfig($params)
    {
        if ($params['userAmoIdUfCode']) $this->userAmoIdUfCode = $params['userAmoIdUfCode'];
        if ($params['userGroupsToSync']) $this->userGroupsToSync = $params['userGroupsToSync'];
        if ($params['userGroupsFieldId']) $this->userGroupsFieldId = $params['userGroupsFieldId'];
        if ($params['contactCustomFields']) $this->contactCustomFields = $params['contactCustomFields'];
        if ($params['goodAmoIdPropCode']) $this->goodAmoIdPropCode = $params['goodAmoIdPropCode'];
        if ($params['goodCustomFields']) $this->goodCustomFields = $params['goodCustomFields'];
        if ($params['dealAmoIdPropId']) $this->dealAmoIdPropId = $params['dealAmoIdPropId'];
        if ($params['dealCustomFields']) $this->dealCustomFields = $params['dealCustomFields'];
        if ($params['dealStatuses']) $this->dealStatuses = $params['dealStatuses'];
        if ($params['logEvents']) $this->logEvents = $params['logEvents'];
        if ($params['logErrors']) $this->logErrors = $params['logErrors'];
    }

    /**
     * Log
     *
     * @param $data
     */
    private function logIt($data, $file)
    {
        $row = '[' . date('Y.m.d H:i:s') . '] ' . $data . PHP_EOL;
        file_put_contents($file, $row, FILE_APPEND | LOCK_EX);
    }

    private function logEvent($data)
    {
        if ($this->logEvents) {
            $this->logIt($data, __DIR__ . '/AmoCRM_bx.log');
        }
    }

    private function logError($data)
    {
        if ($this->logErrors) {
            $this->logIt('[ERROR]' . $data, __DIR__ . '/AmoCRM_bx_errors.log');
        }
    }

    /**
     * Подготовка массива для метода
     *
     * @param $arr
     * @return array
     */
    private function makeItArray($arr)
    {
        if (!is_array($arr)) {
            $arr = Array($arr);
        }
        return $arr;
    }

    private function checkArrayNonEmpty($arr)
    {
        $arr = $this->makeItArray($arr);
        $res = [];
        foreach ($arr as $val) {
            if ($val) {
                $res[] = $val;
            }
        }
        return $res;
    }

    /**
     * Синхронизация клиентов
     *
     * @param $contactIDs
     * @return string
     */
    public function syncContacts($contactIDs)
    {
        $CUser = new \CUser;
        $contactIDs = $this->checkArrayNonEmpty($contactIDs);
        if (count($contactIDs) > 0) {
            // Get user groups
            $arGroups = Array();
            $by = 'ID';
            $order = 'ASC';
            $filter = Array("ID" => implode(' | ', $this->userGroupsToSync));
            $dbRes = \CGroup::GetList($by, $order, $filter, 'N');
            while ($arGroup = $dbRes->Fetch()) {
                $arGroups[$arGroup['ID']] = $arGroup;
            }
            // Get users
            $by = 'ID';
            $order = 'ASC';
            $arFilter = Array('ID' => implode(' | ', $contactIDs));
            $arParams = Array('SELECT' => Array('UF_*'));
            $arAmoIDs = Array();
            $dbRes = \CUser::GetList($by, $order, $arFilter, $arParams);
            while ($user = $dbRes->GetNext()) {
                if ($notFirstTime) {
                    sleep(0.15);
                } else {
                    $notFirstTime = true;
                }
                try {
                    // Prepare data to send
                    $params = new \stdClass();
                    $params->name = $user['NAME'] . ' ' . $user['LAST_NAME'];
                    foreach ($this->contactCustomFields as $amoFieldId => $bxField) {
                        $field = Array();
                        // Set field params
                        if ($bxField['valueByCodeList']) {
                            $field['value'] = $bxField['list'][$user[$bxField['valueByCodeList']]];
                        }
                        if ($bxField['valueByCode']) {
                            $field['value'] = $user[$bxField['valueByCode']];
                        }
                        if ($bxField['enumByCode']) {
                            $field['enum'] = $user[$bxField['enumByCode']];
                        }
                        if ($bxField['value']) {
                            $field['value'] = $bxField['value'];
                        }
                        if ($bxField['enum']) {
                            $field['enum'] = $bxField['enum'];
                        }
                        // Add field to query
                        if (count($field) > 0) {
                            $params->customFields[$amoFieldId][] = $field;
                        }
                    }
                    // Sync groups
                    $syncGroups = Array();
                    $arUserGroups = \CUser::GetUserGroup($user['ID']);
                    foreach ($this->userGroupsToSync as $gid) {
                        if (in_array($gid, $arUserGroups)) {
                            $syncGroups[] = $arGroups[$gid]['NAME'];
                        }
                    }
                    $params->customFields[$this->userGroupsFieldId] = $syncGroups;
                    // AMO query
                    if ($user[$this->userAmoIdUfCode]) {
                        // Update
                        $params->id = $user[$this->userAmoIdUfCode];
                        $amoID = $this->amoApi->updateContact($params);
                        $arAmoIDs[] = $amoID;
                    } else {
                        // Add
                        $amoID = $this->amoApi->createContact($params);
                        $arAmoIDs[$user['ID']] = $amoID;
                        $CUser->Update($user['ID'], Array($this->userAmoIdUfCode => $amoID));
                    }
                } catch (\Exception $e) {
                    $this->logError('syncContacts(): ' . $e->getMessage());
                }
            }
            $result = $arAmoIDs;
        } else {
            $result = 'No contacts id set';
        }
        return $result;
    }

    /**
     * Синхронизация товаров
     *
     * @param $amoCatalogId
     * @param $goodsIDs
     * @param bool $iblock_id
     * @return string
     */
    //public function syncGoods($amoCatalogId, $goodsIDs, $iblock_id = false)
    public function syncGoods($goodsIDs, $iblock_id = false)
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        $CIBlockElement = new \CIBlockElement;
        //if ($amoCatalogId && $goodsIDs) {
        $goodsIDs = $this->checkArrayNonEmpty($goodsIDs);
        if (count($goodsIDs) > 0) {
            // Get prices
            $arOrder = Array('ID' => 'ASC');
            $arFilter = Array('PRODUCT_ID' => $goodsIDs);
            $arSelect = Array();
            $dbRes = \CPrice::GetListEx($arOrder, $arFilter, false, false, $arSelect);
            while ($arPrice = $dbRes->GetNext()) {
                $arPrices[$arPrice['PRODUCT_ID']] = $arPrice['PRICE'];
            }

            // Get goods
            $arOrder = Array('ID' => 'ASC');
            $arFilter = Array('ID' => $goodsIDs);
            if ($iblock_id) {
                $arFilter['IBLOCK_ID'] = $iblock_id;
            }
            $arSelect = Array('ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_*');
            $dbRes = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
            while ($ob = $dbRes->GetNextElement()) {
                if ($notFirstTime) {
                    sleep(0.15);
                } else {
                    $notFirstTime = true;
                }
                try {
                    $good = $ob->GetFields();
                    $props = $ob->GetProperties();
                    foreach ($props as $prop) {
                        $good['PROPERTY_' . $prop['CODE']] = $prop['VALUE'];
                    }
                    $good['PRICE'] = $arPrices[$good['ID']];
                    // Prepare data to send
                    $params = new \stdClass();
                    $params->name = $good['NAME'];
                    $params->request_id = $good['ID'];
                    //$params->catalog_id = $amoCatalogId;
                    foreach ($this->goodCustomFields as $amoFieldId => $bxField) {
                        $field = Array();
                        // Set field params
                        if ($bxField['valueByCodeList']) {
                            $field['value'] = $bxField['list'][$good[$bxField['valueByCodeList']]];
                        }
                        if ($bxField['valueByCode']) {
                            $field['value'] = $good[$bxField['valueByCode']];
                        }
                        if ($bxField['enumByCode']) {
                            $field['enum'] = $good[$bxField['enumByCode']];
                        }
                        if ($bxField['value']) {
                            $field['value'] = $bxField['value'];
                        }
                        if ($bxField['enum']) {
                            $field['enum'] = $bxField['enum'];
                        }
                        // Add field to query
                        if (count($field) > 0) {
                            $params->customFields[$amoFieldId][] = $field;
                        }
                    }
                    // Amo query
                    if ($good['PROPERTY_' . $this->goodAmoIdPropCode]) {
                        // Update
                        $params->id = $good['PROPERTY_' . $this->goodAmoIdPropCode];
                        $amoID = $this->amoApi->updateGood($params);
                        if ($amoID) {
                            $arAmoIDs[] = $amoID;
                        }
                    } else {
                        // Add
                        $amoID = $this->amoApi->createGood($params);
                        if ($amoID) {
                            $arAmoIDs[$good['ID']] = $amoID;
                            $CIBlockElement::SetPropertyValuesEx($good['ID'], $iblock_id, Array($this->goodAmoIdPropCode => $amoID));
                        }
                    }
                } catch (\Exception $e) {
                    $this->logError('syncGoods(): ' . $e->getMessage());
                }
            }
            $result = $arAmoIDs;
        } else {
            $result = 'No goods id or catalog id set';
        }
        return $result;
    }

    /**
     * Синхронизация сделок
     *
     * @param $dealIDs
     * @return array|string
     */
    public function syncDeals($dealIDs)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        \Bitrix\Main\Loader::includeModule('iblock');
        $dealIDs = $this->checkArrayNonEmpty($dealIDs);
        if (count($dealIDs) > 0) {
            $arOrders = [];
            try {
                // Get orders
                $dbRes = \Bitrix\Sale\Order::getList([
                    'filter' => [
                        'ID' => $dealIDs,
                    ],
                    'select' => ['ID', 'USER_ID', 'PRICE', 'STATUS_ID', 'CANCELED', 'PAY_SYSTEM_ID', 'DELIVERY_ID'],
                ]);
                $usersIDs = Array();
                while ($arOrder = $dbRes->fetch()) {
                    $usersIDs[] = $arOrder['USER_ID'];
                    $arOrders[$arOrder['ID']] = $arOrder;
                }
                $usersIDs = array_unique($usersIDs);
                $qRes1 = $this->syncContacts($usersIDs);

                // Get orders basket
                $dbResOrders = \Bitrix\Sale\Basket::getList([
                    'select' => ['ID', 'ORDER_ID', 'PRODUCT_ID', 'NAME', 'QUANTITY', 'PRICE'],
                    'filter' => [
                        'ORDER_ID' => $dealIDs,
                    ],
                ]);
                $goodsIDs = Array();
                while ($arItem = $dbResOrders->fetch()) {
                    $goodsIDs[] = $arItem['PRODUCT_ID'];
                    $arOrders[$arItem['ORDER_ID']]['ITEMS'][$arItem['PRODUCT_ID']] = $arItem;
                }
                $goodsIDs = array_unique($goodsIDs);
                $qRes2 = $this->syncGoods($goodsIDs);

                // Get goods Amo ID
                $goodsAmoIDs = Array();
                //$unsyncGoods = Array();
                if (count($goodsIDs) > 0) {
                    $arOrder = Array('ID' => 'ASC');
                    $arFilter = Array('ID' => $goodsIDs);
                    $arSelect = Array('ID', 'IBLOCK_ID', 'PROPERTY_' . $this->goodAmoIdPropCode);
                    $dbRes = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
                    while ($ob = $dbRes->GetNextElement()) {
                        $good = $ob->GetFields();
                        $props = $ob->GetProperties();
                        if ($props[$this->goodAmoIdPropCode]['VALUE']) {
                            $goodsAmoIDs[$good['ID']] = $props[$this->goodAmoIdPropCode]['VALUE'];
                        } /*else {
                            $unsyncGoods[] = $good['ID'];
                        }*/
                    }
                    /*if (count($unsyncGoods) > 0) {
                        $newGoodsAmoIDs = $this->syncGoods($unsyncGoods);
                        foreach ($newGoodsAmoIDs as $bxID => $amoID) {
                            $goodsAmoIDs[$bxID] = $amoID;
                        }
                    }*/
                }

                // Get users Amo ID
                $usersAmoIDs = Array();
                //$unsyncUsers = Array();
                $by = 'ID';
                $order = 'ASC';
                $arFilter = Array('ID' => implode(' | ', $usersIDs));
                $arParams = Array('SELECT' => Array($this->userAmoIdUfCode));
                $dbRes = \CUser::GetList($by, $order, $arFilter, $arParams);
                while ($user = $dbRes->GetNext()) {
                    if ($user[$this->userAmoIdUfCode]) {
                        $usersAmoIDs[$user['ID']] = $user[$this->userAmoIdUfCode];
                    } /*else {
                        $unsyncUsers[] = $user['ID'];
                    }*/
                }
                /*if (count($unsyncUsers) > 0) {
                    $newUsersAmoIDs = $this->syncContacts($unsyncUsers);
                    foreach ($newUsersAmoIDs as $bxID => $amoID) {
                        $usersAmoIDs[$bxID] = $amoID;
                    }
                }*/

                // Send
                $amoIDs = Array();
                foreach ($arOrders as $id => $arOrder) {
                    if ($notFirstTime) {
                        sleep(0.15);
                    } else {
                        $notFirstTime = true;
                    }
                    try {
                        // КОСТЫЛЬ (товары пока нельзя привязать к сделке через API)
                        foreach ($arOrders[$arOrder['ID']]['ITEMS'] as $item) {
                            $price = number_format($item['PRICE'], 2, '.', '');
                            $qty = number_format($item['QUANTITY'], 0, '', '');
                            $arOrder['ITEMS_STR'] .= $item['NAME'] . ' (' . $price . 'р * ' . $qty . ')' . PHP_EOL;
                        }
                        // Prepare params
                        $params = new \stdClass();
                        $params->name = 'Заказ #' . $arOrder['ID'];
                        $params->sale = $arOrder['PRICE'];
                        $params->contacts_id = $usersAmoIDs[$arOrder['USER_ID']];
                        if ($arOrder['CANCELED'] === 'Y') {
                            $params->status_id = $this->dealStatuses['CANCELED'];
                        } else {
                            $params->status_id = $this->dealStatuses[$arOrder['STATUS_ID']];
                        }
                        foreach ($this->dealCustomFields as $amoFieldId => $bxField) {
                            $field = Array();
                            // Set field params
                            if ($bxField['valueByCodeList']) {
                                $field['value'] = $bxField['list'][$arOrder[$bxField['valueByCodeList']]];
                            }
                            if ($bxField['valueByCode']) {
                                $field['value'] = $arOrder[$bxField['valueByCode']];
                            }
                            if ($bxField['enumByCode']) {
                                $field['enum'] = $arOrder[$bxField['enumByCode']];
                            }
                            if ($bxField['value']) {
                                $field['value'] = $bxField['value'];
                            }
                            if ($bxField['enum']) {
                                $field['enum'] = $bxField['enum'];
                            }
                            // Add field to query
                            if (count($field) > 0) {
                                $params->customFields[$amoFieldId][] = $field;
                            }
                        }
                        // Add or Upd
                        $order = \Bitrix\Sale\Order::load($arOrder['ID']);
                        $propAmoID = $order->getPropertyCollection()->getItemByOrderPropertyId($this->dealAmoIdPropId);
                        $propAmoIdVal = $propAmoID->getValue();
                        if ($propAmoIdVal) {
                            // Update
                            $params->id = $propAmoIdVal;
                            $amoID = $this->amoApi->updateDeal($params);
                        } else {
                            // Add
                            $amoID = $this->amoApi->createDeal($params);
                            if ($amoID) {
                                $propAmoID->setValue($amoID);
                                $order->save();
                            }
                        }
                        $amoIDs[] = $amoID; //$arOrder;
                    } catch (\Exception $e) {
                        $this->logError($e->getMessage());
                    }
                }

                $result = $amoIDs;
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
                $result = 'error';
            }
        } else {
            $result = 'No deals ID set';
        }
        return $result;
    }

    /**
     * Приведение номера мобильного телефона к 79998887766
     *
     * @param $phone
     *
     * @return null|string|string[]
     */
    public function getClearPhone($phone)
    {
        $phone = preg_replace('~[^0-9]+~', '', $phone);

        if ($phone[0] === '8') {
            $phone = '7' . substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            $phone = '7' . $phone;
        }

        return $phone;
    }

}