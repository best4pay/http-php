<?php
header('Content-Type: application/json');
$callback_arr = json_decode(file_get_contents('php://input'),true);


//密钥 可以在商户后台重置密钥得到，重置后之前密钥会失效
$Key = <<<EOD
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDg2Dgxgk2JTck8sJ5k/RlIbMCU
lVD6UFDhLy6/y3+NBSKWClvBvLQBxcB542X+qVXvuI9PcHhs7MYibBO2BjUc/s/w
O98wr0aIHkSg+z65Is3NTD1ofSMZcfuvKebakEJiO+stGSmqTKnWfFiJofeoAFXj
HlUvO+ymrksv/IraxwIDAQAB
-----END PUBLIC KEY-----
EOD;

//uuid向管理员索取
$uuid = '4a748c8a-2f94-468a-9535-f00a73a69a28';

$config = [
    "Key" => $Key, 
    "uuid" => $uuid,
    "timestamp"=>time(),
];



if (empty($callback_arr)){ //没有post数据

    $data = [
        "client_order_no"=>"01234567890123",
        "amount"=>"30",
        "to_account_name"=>"马小云",
        "to_bank_name"=>"中国银行",
        "to_bank_account"=>"65656565656565656",
        "callback_url"=>"https://www.baidu.com",
    ];
    
    $arr = [
        "type"=>"withdraw_request",
        "nonce_hash"=>md5($config['timestamp'].$config['uuid']),
        "uuid"=>$config['uuid'],
        "data"=> $data,
    ];
    
    recursiveKsort($arr); //排序
    
    $config['md5'] =  md5(json_encode($arr,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    
    $arr['token'] =  Encrypt($config);
    
    echo json_encode($arr,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo curl_post($arr);
}else{ //回调消息写入文本文件
    $config['callback_arr'] = $callback_arr;
    $verify = verify($config);
    if ($verify === 1) {
        
        http_response_code(200); //返回200 表示成功
        file_put_contents('./callback_message.txt',json_encode($callback_arr,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND);
        echo '{"uuid":"'.$callback_arr['uuid'].'","type":"order_cancel_reply","nonce_hash":"'.$callback_arr['nonce_hash'].'","data":{"client_order_no":"'.$callback_arr['data']['client_order_no'].'"},"result":"success","msg":"ok"}'; //回复的消息
    } else{
        http_response_code(404);
        echo '验签失败';
    } 

    
}


//加密
function Encrypt($config)
{

    // 加载密钥
    $publicKeyResource = openssl_get_publickey($config['Key']);
    // 执行加密
    if (openssl_public_encrypt($config['uuid'] . "," . $config['timestamp']. "," . $config['md5'], $encryptedData, $publicKeyResource)) {
        // 加密成功
        $encryptedData = base64_encode($encryptedData);
        return $encryptedData;
    } else {
        // 加密失败
        echo  "加密失败，程序已退出";
        exit(); //加密失败退出程序
    }

}

//
function verify($config){
    $jsonData = $config['callback_arr'];//取出callback_arr数组
    
    $signature = $jsonData['signature']; //取出签名
    
    unset($jsonData["signature"]); //删除signature
    
    recursiveKsort($jsonData); //升序排序
    
    $json_str = json_encode($jsonData,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    
    // 创建公钥对象
    $publicKeyObject = openssl_get_publickey($config['Key']);
    // 使用公钥进行验签
    return openssl_verify($json_str, base64_decode($signature), $publicKeyObject, OPENSSL_ALGO_SHA256);

}


//替换url无法处理字符串
function url_safe_base64_encode($str)
{
    $str = str_replace("+", "-", $str);
    $str = str_replace('/', '_', $str);
    $str = str_replace('=', '', $str);
    return $str;
}



//升序排序
function recursiveKsort(&$array) {
    if (!is_array($array)) {
        return;
    }

    ksort($array);
    foreach ($array as &$value) {
        recursiveKsort($value);
    }
}

//post方法
function curl_post($jsonData) {
    
    $url = 'https://server1.best4pay.com';
    // 将 JSON 数据编码为字符串
    $data = json_encode($jsonData,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // 设置请求头
    $headers = array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    );

    // 创建一个 cURL 句柄
    $ch = curl_init();

    // 设置 cURL 选项
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // 执行 cURL 请求并获取响应
    $response = curl_exec($ch);

    // 检查是否有错误发生
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return "cURL Error: " . $error;
    }

    // 关闭 cURL 句柄
    curl_close($ch);

    // 返回响应数据
    return $response;
}


