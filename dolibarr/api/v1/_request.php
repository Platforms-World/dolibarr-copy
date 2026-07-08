<?php  
if (!defined('TAKEPOS_API_V1_REQUEST_INCLUDED')) {  
define('TAKEPOS_API_V1_REQUEST_INCLUDED', 1);  
  
function takeposApiRequestBody()  
{  
static $parsed = null;  
static $loaded = false;  
if ($loaded) { return $parsed; }  
$loaded = true;  
$raw = file_get_contents('php://input');  
if ($raw === false || trim($raw) === '') { $parsed = array(); return $parsed; }  
$data = json_decode($raw, true);  
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) { takeposApiError('INVALID_PARAMETER', 'Invalid JSON body.', 400, array('json_error' => json_last_error_msg())); }  
$parsed = $data;  
return $parsed;  
}  
  
function takeposApiRequestRequireField($data, $field, $message = '')  
{  
if (!is_array($data) || !array_key_exists($field, $data)) { takeposApiError('INVALID_PARAMETER', ($message !== '' ? $message : ($field . ' is required')), 422); }  
return $data[$field];  
}  
} 
