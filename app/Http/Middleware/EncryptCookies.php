<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncrypter;

/**
 * Class EncryptCookies
 * @author annejan@badge.team
 * @package App\Http\Middleware
 */
class EncryptCookies extends BaseEncrypter
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<string>
     */
    protected $except = [
        //
    ];
}
