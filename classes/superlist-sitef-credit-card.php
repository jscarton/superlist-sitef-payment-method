<?php

class SuperlistSitefCreditCard extends SuperlistSitefBase
{
    private $xml_request_debug="";
    private $xml_response_debug="";
    private $current_nit="";
    private $order;
    public function __construct($order=null)
    {
        parent::__construct([]);
        if (!is_null($order))
            $this->order=$order;
        $settings=new SuperlistSitefSettings();
        $this->plugin_settings=$settings->getPluginSettings();
        $this->endpoints=$settings->getEndpoints($this->plugin_settings->sandbox_mode);
        $this->credentials=$settings->getSitefCredentials($this->plugin_settings->sandbox_mode);
    }

    public function dumpIt()
    {
        $arrDump=[
            "settings"=>$this->plugin_settings->dump(),
            "endpoints"=>$this->endpoints->dump(),
            "credentials"=>$this->credentials->dump()
        ];
        return json_encode($arrDump);
    }

    public function beginTransaction ($amount,$orderId="")
    {
        if ($this->plugin_settings->test_mode!='yes')
            $params['amount']=$amount;
        else
            $params['amount']=50;
        $params['orderId']=$orderId;
        $params['merchantId']=$this->credentials->merchant_id;
        $query=$this->buildQuery('beginTransaction','transaction',$params);
        $result=$this->execQuery('beginTransaction',$query);
        return $result;
    }
    
    public function doCardQuery($authorizerId, $cardnumber, $transaction)
    { 
        $params['authorizerId']=$authorizerId;
        $params['cardNumber']=$cardnumber;
        $params['nit']=$transaction;        
        $query=$this->buildQuery('doCardQuery','cardQuery',$params);
        $result=$this->execQuery('doCardQuery',$query);
        return $result;
    }
    public function doGetStatus($transaction)
    {
        $params['merchantKey']=$this->credentials->merchant_key;
        $params['nit']=$transaction;        
        $query=$this->buildQuery('getStatus',null,$params);
        $result=$this->execQuery('getStatus',$query);
        return $result;
    }

    public function doPayment ($authorizerId,$autoConfirmation,$cardExpiryDate,$cardNumber,$cardSecurityCode,$customerId,$extraField="",$nit)
    {
        $this->current_nit=$nit;
        $params['authorizerId']=$authorizerId;
        $params['autoConfirmation']=$autoConfirmation;
        $params['cardExpiryDate']=$cardExpiryDate;
        $params['cardNumber']=$cardNumber;
        $params['cardSecurityCode']=$cardSecurityCode;
        $params['customerId']=$customerId;
        $params['extraField']="";
        $params['installmentType']="4";
        $params['installments']="1";
        $params['nit']=$nit;
        $query=$this->buildQuery('doPayment','payment',$params);
        $result=$this->execQuery('doPayment',$query);
        $this->current_nit=null;
        return $result;
    }

    public function doHashPayment ($authorizerId,$autoConfirmation,$cardHash,$cardSecurityCode,$nit,$customerId)
    {    
        $this->current_nit=$nit;    
        $params['authorizerId']=$authorizerId;
        $params['autoConfirmation']=$autoConfirmation;
        $params['cardHash']=$cardHash;
        $params['cardSecurityCode']=$cardSecurityCode;      
        $params['installmentType']="4";
        $params['installments']="1";
        $params['nit']=$nit;
        $params['customerId']=$customerId;
        $query=$this->buildQuery('doHashPayment','hashPayment',$params);
        $result=$this->execQuery('doHashPayment',$query);
        $this->current_nit=null;
        return $result;        
    }

    public function storeCard ($authorizerId,$cardExpiryDate,$cardNumber,$customerDNI,$customerID)
    {        
        $params['authorizerId']=$authorizerId;
        $params['cardExpiryDate']=$cardExpiryDate;
        $params['cardNumber']=$cardNumber;
        $params['customerId']=$customerDNI;
        $params['merchantUSN']=$customerID;
        $params['merchantId']=$this->credentials->merchant_id;
        $query=$this->buildQuery('store','store',$params,true);
        $result=$this->execQuery('store',$query,true);
        return $result;
    }
    public function beginRemoveStoredCard ($cardHash,$customerID)
    {        
        $params['cardHASH']=$cardHash;
        $params['merchantUSN']=$customerID;
        $params['merchantKey']=$this->credentials->merchant_key;
        $query=$this->buildQuery('beginRemoveStoredCard',null,$params,true);
        $result=$this->execQuery('beginRemoveStoredCard',$query,true);
        return $result;
    }

    public function doRemoveStoredCard ($cardHash,$nita)
    {        
        $params['cardHASH']=$cardHash;
        $params['nita']=$nita;
        $params['merchantKey']=$this->credentials->merchant_key;
        $query=$this->buildQuery('doRemoveStoredCard',null,$params,true);
        $result=$this->execQuery('doRemoveStoredCard',$query,true);
        return $result;
    }

    public function callStatus ($nita)
    {        
        $params['nita']=$nita;        
        $query=$this->buildQuery('callStatus',null,$params,true);
        $result=$this->execQuery('callStatus',$query,true);
        return $result;
    }

    private function buildQuery($action,$prefix=null,$params,$alternate_endpoint=false)
    {
        $xmlns=(!$alternate_endpoint)?"xmlns:ws=\"https://ws.payment2.esitef.softwareexpress.com.br/\"":"xmlns:ws=\"http://ws.recurrent.esitef.softwareexpress.com.br/\"";
        if ($prefix!=null){
            $query="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\"  $xmlns>
               <soapenv:Header></soapenv:Header>
               <soapenv:Body>
                  <ws:$action>
                     <{$prefix}Request>";
        } else{
            $query="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" $xmlns>
               <soapenv:Header></soapenv:Header>
               <soapenv:Body>
                  <ws:$action>";
        }
            foreach ($params as $key=>$value)
                $query.="<$key>$value</$key>";
        if ($prefix!=null){
            $query.="</{$prefix}Request>
                  </ws:$action>
               </soapenv:Body>
            </soapenv:Envelope>";
        }
        else{
         $query.="</ws:$action>
               </soapenv:Body>
            </soapenv:Envelope>";   
        }


        return $query;
    }

    private function execQuery($action,$xml,$alternate_endpoint=false,$try=0)
    {        
        $this->xml_request_debug=$xml;
         $headers = array(
            'Method: POST',
            'Connection: Keep-Alive',
            'User-Agent: PHP-SOAP-CURL',
            'Content-Type: text/xml; charset=utf-8',
        );

        $endpoint2use=(!$alternate_endpoint)?$this->endpoints->payments_custom_url:$this->endpoints->recurrent_custom_url;
        $xmlns=(!$alternate_endpoint)?"https://ws.payment2.esitef.softwareexpress.com.br/":"http://ws.recurrent.esitef.softwareexpress.com.br/";  
        //wp_mail('juan.scarton@superlist.com',"$action Request","<pre>$xml</pre>\n$endpoint2use",[],[]);      
        $ch = curl_init($endpoint2use);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);               
        $exec = curl_exec($ch);       
        add_post_meta($this->order->id,$action."x".$try,json_encode($exec));
        if($exec === false || $exec=="null" || $exec=="" || $exec==null) {
            //wp_mail('juan.scarton@superlist.com',"$action Response","Error:".$exec,[],[]);      
           //echo "ERROR $try:$action<br/>";
           if ($try==2 || $action=="getStatus" || $action=="doRemoveStoredCard"){
                $ret=['result' => false, 'try'=>$try, 'action'=>$action];
                curl_close($ch);
                return $ret;
           }
            else
            {
                curl_close($ch);
                if ($action=='doPayment' || $action=='doHashPayment')
                {                    
                    for ($i=0;$i<3;$i++){
                        $getStatus=$this->doGetStatus($this->current_nit);
                        if (is_object($getStatus) && $getStatus->paymentResponse->responseCode==0)
                        {                                                        
                            return $getStatus;                                        
                        }
                        sleep(2);
                    }
                    if (is_array($getStatus))
                        return $getStatus;
                }
                return $this->execQuery($action,$xml,$alternate_endpoint,$try+1);
            }
        } 
        else {

          try {
            //wp_mail('juan.scarton@superlist.com',"$action Response",$exec,[],[]);      
            curl_close($ch);
             //echo "SUCCESS $try:$action<br/>";
            $this->xml_response_debug=$exec;
            $index=$action."Response";
              $data = simplexml_load_string($exec);              
              $json = json_encode($data->children("http://schemas.xmlsoap.org/soap/envelope/")->Body->children($xmlns)->$index->children());
              $object = json_decode($json);

          } catch(Exception $e) {
              $object = null;
              $exec = null;
          }
        }
        return $object;
    }
    public function retrieveCardDataByUserId($user_id)
    {
        global $wpdb;
        $table_name= $wpdb->prefix . SuperlistSitefSetup::PREFIX."creditcards";
        $rs=$wpdb->get_row( "SELECT * FROM $table_name WHERE payer_id = $user_id");
        if ($rs)
            return new SuperlistSitefBase(['data'=>$rs]);
        return NULL;
    }
    public function getDebugTrace()
    {
        return ['request'=>$this->xml_request_debug,'response'=>$this->xml_response_debug];
    }
    
    public function getCardInfo($card_number)
    {
        //AMEX
        if ($this->startsWith($card_number,[34,37]))
            return 3;
        //VISA
        if ($this->startsWith($card_number,[4]))
            return 280;
        //MASTER
        if ($this->startsWith($card_number,[5]))
            return 2;
        //SODEXO ALIMENTAçao
        if ($this->startsWith($card_number,[6033]))
            return 280;
        //SODEXO GIF
        if ($this->startsWith($card_number,[606068]))
            return 282;
        //SODEXO PREMIUM
        if ($this->startsWith($card_number,[606069]))
            return 283;
        //DINERS
        if ($this->startsWith($card_number,[300,301,302,303,304,305,309,36]))
            return 33;
        return 0;
    }


    public function startsWith($cardNumber,$prefixes=[])
    {
        foreach ($prefixes as $prefix) {
            $pos=strpos($cardNumber,strval($prefix));
            if ( $pos!==false && $pos==0 )
                return true;
        }
        return false;
    }

    public function removeStoredCard($customerId)
    {
        global $wpdb;
        $table_name= $wpdb->prefix . SuperlistSitefSetup::PREFIX."ws_posts";        
        try{
            //retrieve old card
            $oldCard=$this->retrieveCardDataByUserId($customerId);
            if (!is_null($oldCard))
            {                
                //beginRemoveCard
                $beginRemoveStoredCard=$this->beginRemoveStoredCard($oldCard->data->credit_card_token_id,$customerId);                
                if ($beginRemoveStoredCard->status=='OK'){
                    $i=0;
                    while ($i<3)
                    { 
                        // wait for 3 segs to receive confirmation post
                        sleep(5);                        
                        $sql="SELECT * FROM $table_name WHERE merchantUSN = $customerId AND post_date>='".date('Y-m-d')."' AND cancelStore=1 order by id DESC";
                        //wp_mail('juan.scarton@superlist.com',"checking transaction SQL",$sql,[],[]);
                        $rs=$wpdb->get_row( $sql);
                        if ($rs)
                        {
                            $transaction=new SuperlistSitefBase(['data'=>$rs]);
                            //wp_mail('juan.scarton@superlist.com',"checking transaction results","result:".json_encode($transaction->dump()),[],[]);
                            //doRemoveCard
                            $this->doRemoveStoredCard($oldCard->data->credit_card_token_id,$transaction->data->nita);                            
                            $j=0;
                            while ($j<5)
                            {
                                $statusRemoveCard=$this->callStatus($transaction->data->nita);
                                if ($statusRemoveCard->status=='OK')
                                {
                                        sleep(15);
                                        $sql="SELECT * FROM $table_name WHERE merchantUSN = $customerId AND post_date>='".date('Y-m-d')."' AND cancelStore=1 AND id> {$transaction->data->id} order by id DESC";
                                        $rs=$wpdb->get_row( $sql);
                                        //wp_mail('juan.scarton@superlist.com',"checking callStatus results","result:".json_encode($rs),[],[]);
                                        if ($rs)
                                        {
                                            $status=new SuperlistSitefBase(['data'=>$rs]);
                                            if ($status->data->status=='CON')
                                                return true;                                            
                                        }
                                }
                                $j++;
                            }
                            //if after 4 times isn't CAN then return false;
                            return false;
                        }
                        $i++;
                    }
                    throw new Exception("erro de conexão com gateway de pagamento, por favor tente novamente(501)", 500);
                }
                else
                {
                    if ($this->startsWith($beginRemoveStoredCard->status,["Card not found:"]))
                        return true;
                    else
                        throw new Exception("erro de conexão com gateway de pagamento, por favor tente novamente (502)", 500);
                }

            }
            else
                return true;
        }
        catch (Exception $e)
        {
            return json_decode(json_encode(['error'=>1,'message'=>$e->getMessage()]));
        }
    }
}