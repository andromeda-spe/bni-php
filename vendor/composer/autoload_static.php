<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit29fff19781b72b650020f1b947183056
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Wawatprigala\\BniPhp\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Wawatprigala\\BniPhp\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit29fff19781b72b650020f1b947183056::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit29fff19781b72b650020f1b947183056::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit29fff19781b72b650020f1b947183056::$classMap;

        }, null, ClassLoader::class);
    }
}
