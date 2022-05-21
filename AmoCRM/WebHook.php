<?php

namespace Prominado\AmoCRM;


use Bitrix\Tasks\Exception;

class WebHook
{

    protected $token;
    protected $subdomain;
    protected $amoApiUserId;
    protected $userGroupsToSync = Array();
    protected $userGroupsFieldId;
    protected $contactCustomFields;
    protected $dealCustomFields;
    protected $dealStatuses = Array();
    protected $logEvents = true;
    protected $logErrors = true;

    /**
     * AmoApi constructor.
     */
    public function __construct($params)
    {
        $this->setConfig($params);
    }

    /**
     * Конфигурация
     *
     * @param $params
     */
    public function setConfig($params)
    {
        if ($params['token']) $this->token = $params['token'];
        if ($params['subdomain']) $this->subdomain = $params['subdomain'];
        if ($params['amoApiUserId']) $this->amoApiUserId = $params['amoApiUserId'];
        if ($params['userGroupsToSync']) $this->userGroupsToSync = $params['userGroupsToSync'];
        if ($params['userGroupsFieldId']) $this->userGroupsFieldId = $params['userGroupsFieldId'];
        if ($params['contactCustomFields']) $this->contactCustomFields = $params['contactCustomFields'];
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
            $this->logIt($data, __DIR__ . '/AmoCRM_wh.log');
        }
    }

    private function logError($data)
    {
        if ($this->logErrors) {
            $this->logIt('[ERROR]' . $data, __DIR__ . '/AmoCRM_wh_errors.log');
        }
    }


    /**
     * Init
     *
     * @param $GET
     * @param $POST
     * @return string
     */
    public function init($REQUEST)
    {
        $this->logEvent('Incoming query:' . PHP_EOL . print_r($REQUEST));
        if ($REQUEST['token'] == $this->token && $REQUEST['account']['subdomain'] == $this->subdomain) {
            if ($REQUEST['contacts']) {
                foreach ($REQUEST['contacts']['update'] as $contact) {
                    if ($contact['modified_user_id'] != $this->amoApiUserId) $this->syncContact($contact);
                }
            }
            if ($REQUEST['leads']) {
                foreach ($REQUEST['leads']['update'] as $lead) {
                    if ($lead['modified_user_id'] != $this->amoApiUserId) $this->syncDeal($lead);
                }
            }
        } else {
            $result = 'Wrong subdomain or token';
            $this->logError('Wrong subdomain or token');
        }
        return $result;
    }

    /**
     * @param $fields
     * @return string
     */
    protected function syncContact($amoFields)
    {
        $name = explode(' ', $amoFields['name']);
        $fields['NAME'] = $name[0];
        $fields['LAST_NAME'] = $name[1];
        foreach ($amoFields['custom_fields'] as $cField) {
            if ($cField['id'] == $this->userGroupsFieldId) {
                // Sync groups
                $syncGroups = Array();
                foreach ($cField['values'] as $value) {
                    $syncGroups[] = $this->userGroupsToSync[$value['enum']];
                }
            } else {
                // Sync custom fields
                $fieldConf = $this->contactCustomFields[$cField['id']];
                if ($fieldConf) {
                    if ($fieldConf['valueByCodeList']) {
                        $enumAmoId = array_shift($cField['values'])['enum'];
                        $fields[$fieldConf['valueByCodeList']] = $fieldConf['list'][$enumAmoId];
                    } elseif ($fieldConf['enumAmoId']) {
                        foreach ($cField['values'] as $value) {
                            if ($value['enum'] == $fieldConf['enumAmoId']) {
                                $fields[$fieldConf['valueByCode']] = $value['value'];
                            }
                        }
                    } else {
                        $fields[$fieldConf['valueByCode']] = array_shift($cField['values'])['value'];
                    }
                }
            }
        }
        if ($fields['ID']) {
            $id = $fields['ID'];
            unset($fields['ID']);
            // Sync groups
            $arUserGroupsNew = Array();
            $arUserGroups = \CUser::GetUserGroup($id);
            foreach ($arUserGroups as $gid) {
                if (!in_array($gid, $this->userGroupsToSync)) {
                    $arUserGroupsNew[] = $gid;
                }
            }
            if(count($syncGroups) > 0){
                $arUserGroupsNew = array_merge($arUserGroupsNew, $syncGroups);
            }
            \CUser::SetUserGroup($id, $arUserGroupsNew);
            // Update
            $user = new \CUser;
            $user->Update($id, $fields);
        } else {
            // Add
            // nothing
        }
        $this->logEvent(print_r($fields, true));
        return '';
    }

    /**
     * @param $fields
     * @return string
     */
    protected function syncDeal($amoFields)
    {
        \Bitrix\Main\Loader::includeModule('sale');
        $fields['STATUS_ID'] = $this->dealStatuses[$amoFields['status_id']];
        foreach ($amoFields['custom_fields'] as $cField) {
            $fieldConf = $this->dealCustomFields[$cField['id']];
            if ($fieldConf) {
                if ($fieldConf['valueByCodeList']) {
                    $enumAmoId = array_shift($cField['values'])['enum'];
                    $fieldConf['listFlipped'] = array_flip($fieldConf['list']);
                    $fields[$fieldConf['valueByCodeList']] = $fieldConf['listFlipped'][$enumAmoId];
                } elseif ($fieldConf['enumAmoId']) {
                    foreach ($cField['values'] as $value) {
                        if ($value['enum'] == $fieldConf['enumAmoId']) {
                            $fields[$fieldConf['valueByCode']] = $value['value'];
                        }
                    }
                } elseif ($fieldConf['valueByCode']) {
                    $fields[$fieldConf['valueByCode']] = array_shift($cField['values'])['value'];
                }
            }
        }
        if ($fields['ID']) {
            // Update
            try {
                $order = \Bitrix\Sale\Order::load($fields['ID']);
                unset($fields['ID']);
                if($fields['STATUS_ID']){
                    $order->setField('STATUS_ID', $fields['STATUS_ID']);
                }
                unset($fields['STATUS_ID']);
                if($fields['PAY_SYSTEM_ID']){
                    $this->logEvent('PSID: ' . $fields['PAY_SYSTEM_ID']);
                    $paySystemService = \Bitrix\Sale\PaySystem\Manager::getById($fields['PAY_SYSTEM_ID']);
                    $paymentCollection = $order->getPaymentCollection();
                    $payment = $paymentCollection[0];
                    $payment->setFields(array(
                        'PAY_SYSTEM_ID' => $paySystemService["ID"], //$paySystemService["PAY_SYSTEM_ID"],
                        'PAY_SYSTEM_NAME' => $paySystemService["NAME"],
                    ));
                }
                unset($fields['PAY_SYSTEM_ID']);
                if($fields['DELIVERY_ID']){
                    $shipmentService = \Bitrix\Sale\Delivery\Services\Manager::getById($fields['DELIVERY_ID']);
                    $shipmentCollection = $order->getShipmentCollection();
                    $shipment = $shipmentCollection[0];
                    $shipment->setFields(array(
                        'DELIVERY_ID' => $shipmentService['ID'],
                        'DELIVERY_NAME' => $shipmentService['NAME'],
                    ));
                }
                unset($fields['DELIVERY_ID']);
                foreach ($fields as $key => $val) {
                    if($key){
                        $order->setField($key, $val);
                    }
                }
                $order->save();
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
            }
        } else {
            // Add
            // Nothing
        }
        $this->logEvent(print_r($fields, true));
        return '';
    }

}