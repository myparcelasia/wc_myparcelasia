<?php











namespace Composer;

use Composer\Semver\VersionParser;






class InstalledVersions
{
private static $installed = array (
  'root' => 
  array (
    'pretty_version' => 'dev-main',
    'version' => 'dev-main',
    'aliases' => 
    array (
    ),
    'reference' => 'd010bd31aad07f7453b6f903a76cee9bf93b1ffd',
    'name' => '__root__',
  ),
  'versions' => 
  array (
    '__root__' => 
    array (
      'pretty_version' => 'dev-main',
      'version' => 'dev-main',
      'aliases' => 
      array (
      ),
      'reference' => 'd010bd31aad07f7453b6f903a76cee9bf93b1ffd',
    ),
    'graham-campbell/result-type' => 
    array (
      'pretty_version' => 'v1.0.4',
      'version' => '1.0.4.0',
      'aliases' => 
      array (
      ),
      'reference' => '0690bde05318336c7221785f2a932467f98b64ca',
    ),
    'phpoption/phpoption' => 
    array (
      'pretty_version' => '1.8.1',
      'version' => '1.8.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'eab7a0df01fe2344d172bff4cd6dbd3f8b84ad15',
    ),
    'symfony/polyfill-ctype' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '30885182c981ab175d4d034db0f6f469898070ab',
    ),
    'symfony/polyfill-mbstring' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '0abb51d2f102e00a4eefcf46ba7fec406d245825',
    ),
    'symfony/polyfill-php80' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '57b712b08eddb97c762a8caa32c84e037892d2e9',
    ),
    'symfony/var-dumper' => 
    array (
      'pretty_version' => 'v3.4.47',
      'version' => '3.4.47.0',
      'aliases' => 
      array (
      ),
      'reference' => '0719f6cf4633a38b2c1585140998579ce23b4b7d',
    ),
    'vlucas/phpdotenv' => 
    array (
      'pretty_version' => 'v5.4.1',
      'version' => '5.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '264dce589e7ce37a7ba99cb901eed8249fbec92f',
    ),
    'wanfeiyy/dd' => 
    array (
      'pretty_version' => 'v1.0.3',
      'version' => '1.0.3.0',
      'aliases' => 
      array (
      ),
      'reference' => '7a2d3f58e2320e3dd7b1f366f6931163c47ba772',
    ),
  ),
);







public static function getInstalledPackages()
{
return array_keys(self::$installed['versions']);
}









public static function isInstalled($packageName)
{
return isset(self::$installed['versions'][$packageName]);
}














public static function satisfies(VersionParser $parser, $packageName, $constraint)
{
$constraint = $parser->parseConstraints($constraint);
$provided = $parser->parseConstraints(self::getVersionRanges($packageName));

return $provided->matches($constraint);
}










public static function getVersionRanges($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

$ranges = array();
if (isset(self::$installed['versions'][$packageName]['pretty_version'])) {
$ranges[] = self::$installed['versions'][$packageName]['pretty_version'];
}
if (array_key_exists('aliases', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['aliases']);
}
if (array_key_exists('replaced', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['replaced']);
}
if (array_key_exists('provided', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['provided']);
}

return implode(' || ', $ranges);
}





public static function getVersion($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['version'])) {
return null;
}

return self::$installed['versions'][$packageName]['version'];
}





public static function getPrettyVersion($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['pretty_version'])) {
return null;
}

return self::$installed['versions'][$packageName]['pretty_version'];
}





public static function getReference($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['reference'])) {
return null;
}

return self::$installed['versions'][$packageName]['reference'];
}





public static function getRootPackage()
{
return self::$installed['root'];
}







public static function getRawData()
{
return self::$installed;
}



















public static function reload($data)
{
self::$installed = $data;
}
}
