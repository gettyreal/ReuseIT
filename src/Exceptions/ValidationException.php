<?php
namespace ReuseIT\Exceptions;

class ValidationException extends \Exception {
    private array $errors = [];
    
    public function __construct(array $errors = []) {
        $this->errors = $errors;
        parent::__construct('Validation failed');
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
}
