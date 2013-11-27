<?php

class Helper
{
    protected static $_tmp_file_used = null;

    public static function getTmpFile()
    {
        if (is_null(self::$_tmp_file_used)) {
            self::$_tmp_file_used = array();
            register_shutdown_function(array('Helper', 'deleteTmpFile'));
        }
        $file_name = tempnam('', 'HelperGetTmpFile-');
        unlink($file_name);
        self::$_tmp_file_used[] = $file_name;
        return $file_name;
    }

    public static function deleteTmpFile()
    {
        foreach (self::$_tmp_file_used as $file) {
            self::deleteFile($file);
        }
    }

    protected static function deleteFile($file)
    {
        if (is_dir($file)) {
            $d = opendir($file);
            while ($f = readdir($d)) {
                if ($f == '.' or $f == '..') {
                    continue;
                }
                self::deleteFile($file . '/' . $f);
            }
            rmdir($file);
        } else if (is_file($file)) {
            unlink($file);
        }
    }

    public static function getSrs()
    {
        return array(
            'twd97' => array(
                'name' => 'TWD97',
                'config' => '+proj=tmerc +lat_0=0 +lon_0=121 +k=0.9999 +x_0=250000 +y_0=0 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
            ),
            'twd97/119' => array(
                'name' => 'TWD97 / TM2 zone 119(PengHu, Kinmen, Matsu)',
                'config' => '+proj=tmerc +lat_0=0 +lon_0=119 +k=0.9999 +x_0=250000 +y_0=0 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
            ),
        );
    }
}
