<?php




if(!class_exists('PathSetter')) {
	class PathSetter
	{
	    public static function init(): array
	    {

			PathInspector::defineConstants([
			    'WH_PATH' => 'server.DOCUMENT_ROOT',
			    'WH_URL'  => 'urls.base_url',
			    'ABSPATH'  => 'server.DOCUMENT_ROOT',
			]);
			$data = PathInspector::collect();
			return $data; 

	    }
	}
}











