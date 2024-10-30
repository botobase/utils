<?php

namespace Yabx\Botobase\Dto;

final readonly class Proxy {
    public function __construct(
        public int    $id,
        //public string $host,
        //public int    $port,
        //public string $username,
        //public string $password,
        //public string $type,
        public string $string,
    ) {}
}