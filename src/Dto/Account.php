<?php

namespace Yabx\Botobase\Dto;

final readonly class Account {
    public function __construct(
        public int     $id,
        //public Service $service,
        //public string  $name,
        public ?Proxy   $proxy,
        public string  $credentials,
        public array $extra,
    ) {}
}