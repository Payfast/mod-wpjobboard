<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit681a3ab5b6d33c9a799ae327de83656f
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Payfast\\PayfastCommon\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Payfast\\PayfastCommon\\' => 
        array (
            0 => __DIR__ . '/..' . '/payfast/payfast-common/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit681a3ab5b6d33c9a799ae327de83656f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit681a3ab5b6d33c9a799ae327de83656f::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit681a3ab5b6d33c9a799ae327de83656f::$classMap;

        }, null, ClassLoader::class);
    }
}
