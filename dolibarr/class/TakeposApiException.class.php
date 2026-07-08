<?php 
/**
 * Structured API exception for TakePOS REST endpoints.
 */
class TakeposApiException extends Exception
{
 protected $apiCode = 'INTERNAL_ERROR';

 protected $httpStatus = 500;

 protected $details = null;

 public function __construct($apiCode, $message, $httpStatus = 500, $details = null, Throwable $previous = null)
 {
 parent::__construct((string) $message, 0, $previous);

 $this->apiCode = (string) $apiCode;
 $this->httpStatus = (int) $httpStatus;
 $this->details = $details;
 }

 public function getApiCode()
 {
 return $this->apiCode;
 }

 public function getHttpStatus()
 {
 return $this->httpStatus;
 }

 public function getDetails()
 {
 return $this->details;
 }
}
