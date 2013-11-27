<?php

class ApiController extends Pix_Controller
{
    public function downloadurlAction()
    {
        $url = $_GET['url'];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(array('error' => true, 'message' => 'not valid json'));
        }

        $curl = curl_init($url);
        $download_fp = tmpfile();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FILE, $download_fp);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate'); 
        curl_exec($curl);
        curl_close($curl);
        fflush($download_fp);

        $unzip_script = getenv('UNZIP_SCRIPT') ?: 'unzip';
        $download_file_path = stream_get_meta_data($download_fp)['uri'];

        $target_dir = Helper::getTmpFile();
        $cmd = "{$unzip_script} " . escapeshellarg($download_file_path) . " -d " . escapeshellarg($target_dir);
        exec($cmd, $outputs, $ret);
        if ($ret) {
            return $this->json(array('error' => true, 'message' => 'not valid zip file', 'outputs' => $outputs));
        }
        fclose($download_fp);

        if (!glob($target_dir . '/*.[sS][Hh][Pp]')) {
            return $this->json(array('error' => true, 'message' => 'no shp file in zip file'));
        }
        $tmp_file_name = 'shp2json-' . date('Y-m-d-H-i-s') . '-' . uniqid(microtime(true));
        rename($target_dir, '/tmp/' . $tmp_file_name);
        return $this->json(array(
            'error' => false,
            'file_id' => $tmp_file_name,
            'getshop_api' => 'http://' . $_SERVER['HTTP_HOST'] . '/api/getshpfromfile?file_id=' . urlencode($tmp_file_name),
        ));
    }

    public function getshpfromfileAction()
    {
        $file_id = $_GET['file_id'];
        if (!preg_match('#^shp2json-[a-z0-9.-]*$#', $file_id) or !file_exists("/tmp/" . $file_id) or !is_dir("/tmp/" . $file_id)) {
            return $this->json(array('error' => true, 'message' => 'file not found'));
        }

        $target_dir = '/tmp/' . $file_id;
        $ret = array();
        foreach (glob($target_dir . '/*.[sS][Hh][Pp]') as $path) {
            $file_name = substr($path, strlen($target_dir) + 1);
            $ret[] = array(
                'file' => $file_name,
                'geojson_api' => 'http://' . $_SERVER['HTTP_HOST'] . '/api/getgeojson?file_id=' . urlencode($file_id) . '&shp_file=' . urlencode($file_name),
            );
        }

        return $this->json(array(
            'error' => false,
            'data' => $ret,
        ));
    }

    public function getgeojsonAction()
    {
        $file_id = $_GET['file_id'];
        if (!preg_match('#^shp2json-[a-z0-9.-]*$#', $file_id) or !file_exists("/tmp/" . $file_id) or !is_dir("/tmp/" . $file_id)) {
            return $this->json(array('error' => true, 'message' => 'file_id not found'));
        }

        $shp_file = $_GET['shp_file'];
        if (strpos($shp_file, '.') === 0 or strpos($shp_file, '/') === 0 or strpos($shp_file, '..')) {
            return $this->json(array('error' => true, 'message' => 'shp not found'));
        }

        $shp_path = '/tmp/' . $file_id . '/' . $shp_file;
        $ogr2ogr_script = getenv('OGR2OGR_SCRIPT') ?: 'ogr2ogr';
        $target_file = Helper::getTmpFile();
        $t_srs = 'EPSG:4326';
        if ($_GET['source_srs']) {
            $s_srs = $_GET['source_srs'];
        } else {
            $s_srs = 'EPSG:4326'; 
        }
        $cmd = "{$ogr2ogr_script} -t_srs " . escapeshellarg($t_srs) . " -s_srs " . escapeshellarg($s_srs) . " -f geojson " . escapeshellarg($target_file) . " " . escapeshellarg($shp_path);
        exec($cmd, $outputs, $ret);
        if ($ret) {
            return $this->json(array(
                'error' => true,
                'message' => 'convert to geojson failed',
                'outputs' => $outputs,
                'cmd' => $cmd,
            ));
        }

        header('Content-Type: application/json');
        readfile($target_file);
        return $this->noview();
    }
}
