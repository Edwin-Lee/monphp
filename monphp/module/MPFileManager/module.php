<?php

class MPFileManager
{
    // {{{ properties
    protected static $file_path = '';
    protected static $web_path = '';
    protected static $sizes = array();
    // }}}
    // {{{ constants 
    const MODULE_AUTHOR = 'Jason T. Wong';
    const MODULE_DESCRIPTION = 'MPFileManager Module';
    const MODULE_WEBSITE = 'www.jasontwong.com';
    const MODULE_DEPENDENCY = '';

    // }}}
    // {{{ constructor
    /**
     * @param int $state current state of module manager
     */
    public function __construct()
    {
    }

    // }}}

    // {{{ public function hook_mpadmin_enqueue_css()
    public function hook_mpadmin_enqueue_css()
    {
        if (strpos(URI_PATH, '/admin/mod/MPFileManager/') === 0)
        {
            mp_enqueue_style('mpadmin_screen', '/admin/static/MPAdmin/screen.css');
            mp_enqueue_style('mpfilemanager_browse', '/admin/static/MPFileManager/browse.css');
        }
        else
        {
            mp_enqueue_style('mpfilemanager_field', '/admin/static/MPFileManager/field.css');
        }
    }

    // }}}
    // {{{ public function hook_mpadmin_enqueue_js()
    public function hook_mpadmin_enqueue_js()
    {
        mp_enqueue_script(
            'mpfilemanager_windowmsg',
            '/admin/static/MPFileManager/jquery.windowmsg-1.0.js',
            array('jquery'),
            FALSE,
            TRUE
        );
        if (strpos(URI_PATH, '/mod/MPFileManager/') !== FALSE)
        {
            mp_enqueue_script(
                'mpadmin_admin',
                '/admin/static/MPAdmin/admin.js',
                array('jquery')
            );
        }
        if (strpos(URI_PATH, '/admin/mod/MPFileManager/browse/tinymce/') !== FALSE)
        {
            mp_enqueue_script(
                'mpfilemanager_tinymce_browse',
                '/admin/static/MPFileManager/tinymce_browse.js',
                array('jquery-tinymce', 'tiny_mce_popup')
            );
        }
        if (strpos(URI_PATH, '/mod/MPFileManager/browse/') !== FALSE)
        {
            mp_enqueue_script(
                'mpfilemanager_filemanager',
                '/admin/static/MPFileManager/filemanager.js',
                array('jquery', 'mpfilemanager_windowmsg'),
                FALSE,
                TRUE
            );
        }
        else
        {
            mp_enqueue_script(
                'mpfilemanager_admin_nav',
                '/admin/static/MPFileManager/admin_nav.js',
                array('jquery'),
                FALSE,
                TRUE
            );
            mp_enqueue_script(
                'mpfilemanager_field',
                '/admin/static/MPFileManager/field.js',
                array('jquery'),
                FALSE,
                TRUE
            );
            mp_enqueue_script(
                'mpfilemanager_tinymce',
                '/admin/static/MPFileManager/tinymce.js',
                array('jquery'),
                FALSE,
                TRUE
            );
        }
    }

    // }}}
    // {{{ public function hook_mpadmin_module_page($page)
    public function hook_mpadmin_module_page($page)
    {
    }
    
    // }}}
    // {{{ public function hook_mpadmin_tinymce()
    public function hook_mpadmin_tinymce()
    {
        return array(
            'file_browser_callback' => 'MPFileManager_browser'
        );
    }
    
    // }}}
    // {{{ public function hook_mpadmin_rpc($function, $data)
    public function hook_mpadmin_rpc($function, $data)
    {
        switch ($function)
        {
            // {{{ case 'browser'
            case 'browser':
                $success = FALSE;
                $action = $data['action'];
                $view = $data['view'];
                $dir = $data['dir'];
                $files = json_decode($data['files'], TRUE);
                $web = self::$web_path . str_replace(self::$file_path, '', $dir);
                $info = array();
                switch ($action)
                {
                    // {{{ case 'add'
                    case 'add':
                        if (count($files) === 1)
                        {
                            $new_dir = $dir.'/'.$files[0];
                            $success = mkdir($new_dir);
                            if ($success)
                            {
                                mkdir($new_dir.'/_resized');
                                chmod($new_dir);
                                $action = 'refresh';
                            }
                        }
                    break;
                    // }}}
                    // {{{ case 'copy'
                    case 'copy':
                        $old_dir = array_pop($files);
                        if (is_dir($old_dir) && is_dir($dir))
                        {
                            foreach ($files as $v)
                            {
                                $old_file = $old_dir.'/'.$v;
                                $new_file = $dir.'/'.$v;
                                if (is_file($old_file))
                                {
                                    $success = copy($old_file, $new_file);
                                    if (!$success)
                                    {
                                        break;
                                    }
                                    mkdir($dir.'/_resized');
                                    $pinfo = pathinfo($v);
                                    $name = $pinfo['filename'];
                                    $ext = $pinfo['extension'];
                                    foreach (array_keys(self::$sizes) as $label)
                                    {
                                        $fname = '/_resized/'.$name.'-'.$label.'.'.$ext;
                                        if (is_file($old_dir.$fname))
                                        {
                                            copy($old_dir.$fname, $dir.$fname);
                                        }
                                    }
                                }
                                elseif (is_dir($old_file))
                                {
                                    $success = dir_copy($old_file, $new_file);
                                    if (!$success)
                                    {
                                        break;
                                    }
                                }
                            }
                            $action = 'refresh';
                        }
                    break;
                    // }}}
                    // {{{ case 'delete'
                    case 'delete':
                        foreach ($files as $v)
                        {
                            $file = $dir.'/'.$v;
                            if (is_file($file))
                            {
                                $success = unlink($file);
                                if ($success && is_dir($dir.'/_resized'))
                                {
                                    $pinfo = pathinfo($v);
                                    $name = $pinfo['filename'];
                                    $ext = $pinfo['extension'];
                                    foreach (array_keys(self::$sizes) as $label)
                                    {
                                        $fname = '/_resized/'.$name.'-'.$label.'.'.$ext;
                                        if (is_file($dir.$fname))
                                        {
                                            unlink($dir.$fname);
                                        }
                                    }
                                }
                            }
                            elseif (is_dir($file))
                            {
                                $success = rm_resource_dir($file);
                            }
                        }
                        $action = 'refresh';
                    break;
                    // }}}
                    // {{{ case 'list'
                    case 'list':
                        $dir_files = scandir($dir);
                        $tmp_sort_dirs = $tmp_sort_files = $tmp_files = $tmp_dirs = array();
                        $tmp_size = 0;
                        foreach ($dir_files as $v)
                        {
                            if (strpos($v,'.') === 0 || $v === '_resized')
                            {
                                continue;
                            }
                            $mime = explode('/', finfo::file($dir.'/'.$v, FILEINFO_MIME_TYPE));
                            $stat = stat($dir.'/'.$v);
                            $stat['nice_mtime'] = date('Y-m-d H:i:s', $stat['mtime']);
                            $stat['nice_size'] = size_readable($stat['size']);
                            if (is_dir($dir.'/'.$v))
                            {
                                $tmp_dirs[] = array(
                                    'name' => $v,
                                    'stat' => $stat,
                                    'mime' => array('folder'),
                                    'ext' => '',
                                    'resized_path' => '',
                                );
                                $tmp_sort_dirs[] = $v;
                            }
                            else
                            {
                                $pinfo = pathinfo($v);
                                if ($mime[0] === 'image')
                                {
                                    $tmp_files[] = array(
                                        'name' => $v,
                                        'stat' => $stat,
                                        'mime' => $mime,
                                        'ext' => $pinfo['extension'],
                                        'resized_path' => self::get_resized_image($web.'/'.$v, 'browse'),
                                    );
                                    $tmp_sort_files[] = $v;
                                }
                                elseif ($view !== 'image')
                                {
                                    $tmp_files[] = array(
                                        'name' => $v,
                                        'stat' => $stat,
                                        'mime' => $mime,
                                        'ext' => $pinfo['extension'],
                                        'resized_path' => '',
                                    );
                                    $tmp_sort_files[] = $v;
                                }
                                else
                                {
                                    continue;
                                }
                            }
                            $tmp_size += $stat['size'];
                        }
                        $files = array_merge($tmp_dirs, $tmp_files);
                        $success = TRUE;
                        $info['total_size'] = size_readable($tmp_size);
                        $info['total_dirs'] = count($tmp_dirs);
                        $info['total_files'] = count($tmp_files);
                    break;
                    // }}}
                    // {{{ case 'move'
                    case 'move':
                        $old_dir = array_pop($files);
                        if (is_dir($old_dir) && is_dir($dir))
                        {
                            foreach ($files as $v)
                            {
                                $old_file = $old_dir.'/'.$v;
                                $new_file = $dir.'/'.$v;
                                if (file_exists($old_file))
                                {
                                    $success = rename($old_file, $new_file);
                                    if (!$success)
                                    {
                                        break;
                                    }
                                }
                            }
                            $action = 'refresh';
                        }
                    break;
                    // }}}
                    // {{{ case 'rename'
                    case 'rename':
                        if (count($files) == 2)
                        {
                            $old_file = $dir.'/'.$files[0];
                            $pinfo = pathinfo($files[0]);
                            $ext = '.'.$pinfo['extension'];
                            $new_file = $dir.'/'.$files[1].$ext;
                            if (file_exists($old_file) && !file_exists($new_file))
                            {
                                $success = rename($old_file, $new_file);
                                if ($success && is_dir($dir.'/_resized'))
                                {
                                    foreach (array_keys(self::$sizes) as $label)
                                    {
                                        $fname = '/_resized/'.$name.'-'.$label.$ext;
                                        $new_fname = '/_resized/'.$files[1].'-'.$label.$ext;
                                        if (is_file($dir.$fname))
                                        {
                                            rename($dir.$fname, $dir.$new_fname);
                                        }
                                    }
                                }
                            }
                            $action = 'refresh';
                        }
                    break;
                    // }}}
                }
                echo json_encode(
                    array(
                        'success' => $success,
                        'action' => $action,
                        'view' => $view,
                        'dir' => $dir,
                        'files' => $files,
                        'web' => $web,
                        'info' => $info,
                    )
                );
            break;
            // }}}
        }
    }

    // }}}
    // {{{ public function hook_mpadmin_settings_fields()
    public function hook_mpadmin_settings_fields()
    {
        $fields = array();
        $fields[] = array(
            'field' => MPField::layout(
                'text',
                array(
                    'data' => array(
                        'label' => 'File path'
                    )
                )
            ),
            'name' => 'file_path',
            'type' => 'text',
            'placeholder' => DIR_FILE . '/upload'
            'value' => array(
                'data' => self::$file_path
            )
        );
        $fields[] = array(
            'field' => MPField::layout(
                'text',
                array(
                    'data' => array(
                        'label' => 'Web path'
                    )
                )
            ),
            'name' => 'web_path',
            'type' => 'text',
            'value' => array(
                'data' => self::$web_path
            )
        );
        $fields[] = array(
            'field' => MPField::layout(
                'filemanager_image_size',
                array(
                    'width' => array(
                        'label' => 'Large Image Size'
                    )
                )
            ),
            'name' => 'size_large',
            'type' => 'filemanager_image_size',
            'value' => array(
                'height' => self::$sizes['large']['height'],
                'width' => self::$sizes['large']['width']
            )
        );
        $fields[] = array(
            'field' => MPField::layout(
                'filemanager_image_size',
                array(
                    'width' => array(
                        'label' => 'Medium Image Size'
                    )
                )
            ),
            'name' => 'size_medium',
            'type' => 'filemanager_image_size',
            'value' => array(
                'height' => self::$sizes['medium']['height'],
                'width' => self::$sizes['medium']['width']
            )
        );
        $fields[] = array(
            'field' => MPField::layout(
                'filemanager_image_size',
                array(
                    'width' => array(
                        'label' => 'Thumbnail Image Size'
                    )
                )
            ),
            'name' => 'size_thumb',
            'type' => 'filemanager_image_size',
            'value' => array(
                'height' => self::$sizes['thumb']['height'],
                'width' => self::$sizes['thumb']['width']
            )
        );
        return $fields;
    }

    // }}}
    // {{{ public function hook_mpadmin_settings_validate($name, $data)
    public function hook_mpadmin_settings_validate($name, $data)
    {
        $success = TRUE;
        switch ($name)
        {
            case 'size_large':
            case 'size_medium':
            case 'size_thumb':
                if (!is_numeric($data['width']) || !is_numeric($data['height']))
                {
                    $data['width'] = '';
                    $data['height'] = '';
                }
        }
        return array(
            'success' => $success,
            'data' => $data
        );
    }

    // }}}
    // {{{ public function hook_mpsystem_active()
    public function hook_mpsystem_active()
    {
        self::$sizes = self::get_image_sizes();
        self::$sizes['browse'] = array(
            'width' => '90',
            'height' => '90',
        );
        self::$file_path = !is_null(MPData::query('MPFileManager', 'file_path'))
            ? MPData::query('MPFileManager', 'file_path')
            : DIR_FILE.'/upload';
        self::$web_path = !is_null(MPData::query('MPFileManager', 'web_path'))
            ? MPData::query('MPFileManager', 'web_path')
            : '/file/upload';
        if (!is_dir(self::$file_path))
        {
            mkdir(self::$file_path, 0777, TRUE);
        }
    }

    // }}}
    // {{{ public function hook_mpuser_perm()
    public function hook_mpuser_perm()
    {
        return array(
            'view files' => 'View Files',
            'create folder' => 'Create Folder',
            'edit folder' => 'Edit Folder',
            'upload file' => 'Upload File',
            'edit file' => 'Edit File'
        );
    }
    // }}}
    // {{{ public function save_file($path, $name, $tmp_file, $type = '')
    public function save_file($path, $name, $tmp_file, $type = '')
    {
        $file = $path.'/'.$name;
        $success = move_uploaded_file($tmp_file, $file);
        return $type === 'image'
            ? MPFile::save_image_set($file, array(), self::$sizes)
            : MPFile::save_file($file)
    }
    // }}}

    // {{{ public static function web_path()
    public static function web_path()
    {
        return self::get_web_path();
    }
    // }}}
    // {{{ public static function get_web_path()
    public static function get_web_path()
    {
        return self::$web_path;
    }
    // }}}
    // {{{ public static function set_web_path($path)
    public static function set_web_path($path)
    {
        MPData::update('MPFileManager', 'web_path', $path);
        self::$web_path = $path;
        return self::$web_path;
    }
    // }}}
    // {{{ public static function file_path()
    public static function file_path()
    {
        return self::get_file_path();
    }
    // }}}
    // {{{ public static function get_file_path()
    public static function get_file_path()
    {
        return self::$file_path;
    }
    // }}}
    // {{{ public static function set_file_path($path)
    public static function set_file_path($path)
    {
        MPData::update('MPFileManager', 'file_path', $path);
        self::$file_path = $path;
        return self::$file_path;
    }
    // }}}
    // {{{ public static function scan($dir)
    public static function scan($dir)
    {
        if (is_dir($dir))
        {
            $contents = array('files' => array(), 'dirs' => array());
            $link_base = str_replace(MPFileManager::file_path(), '', $dir);
            $files = $dirs = array();
            $scans = scandir($dir);
            foreach ($scans as $k => $v)
            {
                $file = $dir.'/'.$v;
                if (is_file($file))
                {
                    $contents['files'][] = array(
                        'link' => $link_base.'/'.$v,
                        'name' => $v,
                        'size' => filesize($file)
                    );
                }
                elseif (is_dir($file))
                {
                    $dir_data = array(
                        'name' => $v
                    );
                    switch ($v)
                    {
                        case '.':
                            $dir_data['link'] = empty($link_base) ? '/' : $link_base;
                        break;
                        case '..':
                            $dir_data['link'] = empty($link_base) ? '/' : dirname($link_base);
                        break;
                        default:
                            $dir_data['link'] = $link_base.'/'.$v;
                        break;
                    }
                    $contents['dirs'][] = $dir_data;
                }
            }
            return $contents;
        }
        elseif (is_file($dir))
        {
            throw new MPFileManagerIsFileException($dir.' is a file');
        }
        else
        {
            throw new MPFileManagerNotExistException($dir.' does not exist');
        }
    }
    // }}}
    // {{{ public static function dir_scan($dir)
    /**
     * Like scan, but just for directories
     * @param string $dir full path to scan
     * @return array
     */
    public static function dir_scan($dir)
    {
        $dirs = array();
        if (file_exists($dir) && is_dir($dir))
        {
            /**
             * using dirname() because we want to see at least the base folder
             * name in the heirarchy
             */
            $the_dir = str_replace(MPFileManager::file_path(), '', $dir);
            if (empty($the_dir))
            {
                $the_dir = '/';
            }
            $dirs = array($the_dir);
            $files = scandir($dir);
            foreach ($files as $file)
            {
                $filepath = $dir.'/'.$file;
                if ($file !== '.' && $file !== '..' && is_dir($filepath))
                {
                    $dirs = array_merge($dirs, self::dir_scan($filepath));
                }
            }
        }
        return $dirs;
    }
    // }}}
    // {{{ public static function get_image_sizes()
    public static function get_image_sizes()
    {
        $default_size = array(
            'width' => '',
            'height' => '',
        );
        $sizes['thumb'] = !is_null(MPData::query('MPFileManager', 'size_thumb'))
            ? MPData::query('MPFileManager', 'size_thumb')
            : $default_size;
        $sizes['medium'] = !is_null(MPData::query('MPFileManager', 'size_medium'))
            ? MPData::query('MPFileManager', 'size_medium')
            : $default_size;
        $sizes['large'] = !is_null(MPData::query('MPFileManager', 'size_large'))
            ? MPData::query('MPFileManager', 'size_large')
            : $default_size;
        return $sizes;
    }
    // }}}
    // {{{ public static function get_resized_image($file, $size)
    public static function get_resized_image($web_file, $size)
    {
        $pinfo = pathinfo($web_file);
        $web_path = dirname($web_file);
        $web_resized = $web_path.'/_resized';
        if (is_string($size) && ake($size, self::$sizes))
        {
            $new_web_file = $web_resized.'/'.$pinfo['filename'].'-'.$size.'.'.$pinfo['extension'];
            if (is_file(self::$file_path.str_replace('/file/upload', '', $new_web_file)))
            {
                return $new_web_file;
            }
        }
        elseif (is_array($size))
        {
        }

        return $web_file;
    }
    // }}}
}

class MPFileManagerNotExistException extends Exception {}
class MPFileManagerIsFileException extends Exception {}
