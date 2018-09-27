<?php

namespace Admin\Controller;

use Service\ResourcesService;

/**
 * 支付方式管理
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class PaymentController extends CommonController
{
    private $payment_dir;

	/**
	 * [_initialize 前置操作-继承公共前置方法]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2016-12-03T12:39:08+0800
	 */
	public function _initialize()
	{
		// 调用父类前置方法
		parent::_initialize();

		// 登录校验
		$this->Is_Login();

		// 权限校验
		$this->Is_Power();

        // 插件目录
        $this->payment_dir = APP_PATH.'Library'.DS.'Payment'.DS;
	}

	/**
     * [Index 支付方式列表]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2016-12-06T21:31:53+0800
     */
	public function Index()
	{
        // 数据列表
        $this->assign('list', $this->GetPaymentList());
        $this->display('Index');
	}

    /**
     * 获取支付插件列表
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     */
    private function GetPaymentList()
    {
        $data = [];
        if(is_dir($this->payment_dir))
        {
            if($dh = opendir($this->payment_dir))
            {
                while(($temp_file = readdir($dh)) !== false)
                {
                    if(substr($temp_file, 0, 1) != '.')
                    {
                        // 获取模块配置信息
                        $payment = htmlentities(str_replace('.class.php', '', $temp_file));
                        $config = $this->GetPaymentConfig($payment);
                        if($config !== false)
                        {
                            // 数据组装
                            $temp = $this->DataAnalysis($config);
                            $temp['id'] = date('YmdHis').GetNumberCode(8);
                            $temp['payment'] = $payment;

                            // 获取数据库配置信息
                            $db_config = ResourcesService::PaymentList(['where'=>['payment'=>$payment]]);
                            if(!empty($db_config[0]))
                            {
                                $temp['is_install'] = 1;
                                $temp['id'] = $db_config[0]['id'];
                                $temp['name'] = $db_config[0]['name'];
                                $temp['logo'] = $db_config[0]['logo'];
                                $temp['apply_terminal'] = $db_config[0]['apply_terminal'];
                                $temp['is_enable'] = $db_config[0]['is_enable'];
                                $temp['config'] = $db_config[0]['config'];
                            }
                            $data[] = $temp;
                        }
                    }
                }
                closedir($dh);
            }
        }
        return $data;
    }

    /**
     * 数据解析
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     * @param   [array]          $data [插件配置信息]
     */
    private function DataAnalysis($data)
    {
        return [
            'name'          => isset($data['base']['name']) ? htmlentities($data['base']['name']) : $payment,
            'version'       => isset($data['base']['version']) ? htmlentities($data['base']['version']) : '',
            'apply_version' => isset($data['base']['apply_version']) ? htmlentities($data['base']['apply_version']) : '',
            'desc'          => isset($data['base']['desc']) ? $data['base']['desc'] : '',
            'author'        => isset($data['base']['author']) ? htmlentities($data['base']['author']) : '',
            'author_url'    => isset($data['base']['author_url']) ? htmlentities($data['base']['author_url']) : '',
            'element'       => isset($data['element']) ? $data['element'] : [],

            'logo'          => '',
            'is_enable'     => 0,
            'is_install'    => 0,
            'apply_terminal'=> array_column(L('common_apply_terminal_list'), 'value'),
            'config'        => '',
        ];
    }

    /**
     * 获取支付模块配置信息
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     * @param   [string]          $payment [模块名称]
     */
    private function GetPaymentConfig($payment)
    {
        $payment = '\Library\Payment\\'.$payment;
        if(class_exists($payment))
        {
            $obj = new $payment();
            if(method_exists($obj, 'Config') && method_exists($obj, 'Pay') && method_exists($obj, 'Respond'))
            {
                return $obj->Config();
            }
        }
        return false;
    }

    /**
     * [SaveInfo 添加/编辑页面]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2016-12-14T21:37:02+0800
     */
    public function SaveInfo()
    {
        // 轮播图片信息
        $data = empty($_REQUEST['id']) ? array() : M('Payment')->find(I('id'));
        $data['apply_terminal'] = empty($data['apply_terminal']) ? [] : json_decode($data['apply_terminal'], true);
        $data['element'] = empty($data['element']) ? [] : json_decode($data['element'], true);
        $data['config'] = empty($data['config']) ? [] : json_decode($data['config'], true);
        //print_r($data['config']);
        $this->assign('data', $data);

        // 适用平台
        $this->assign('common_apply_terminal_list', L('common_apply_terminal_list'));

        // 参数
        $this->assign('param', array_merge($_POST, $_GET));

        $this->display('SaveInfo');
    }

	/**
	 * [Save 支付方式保存]
	 * @author   Devil
	 * @blog     http://gong.gg/
	 * @version  0.0.1
	 * @datetime 2016-12-25T22:36:12+0800
	 */
	public function Save()
	{
		// 是否ajax请求
		if(!IS_AJAX)
		{
			$this->error(L('common_unauthorized_access'));
		}

		// 图片
        $this->ImagesSave('logo', 'file_logo', 'payment');

		// id为空则表示是新增
		$m = D('Payment');

		// 公共额外数据处理
		$_POST['is_enable'] = intval(I('is_enable', 0));

		// 编辑
		if($m->create($_POST, 2))
		{
			// 额外数据处理
			$m->upd_time 			= time();
			$m->apply_terminal 	    = empty($_POST['apply_terminal']) ? '' : json_encode(explode(',', I('apply_terminal')));
			$m->name 				= I('name');
            $m->sort                = intval(I('sort'));

            // 插件配置信息处理
            $m->config = json_encode($this->GetPlugConfig());

			// 移除 id
			unset($m->id);

			// 更新数据库
			if($m->where(array('id'=>I('id')))->save())
			{
				$this->ajaxReturn(L('common_operation_edit_success'));
			} else {
				$this->ajaxReturn(L('common_operation_edit_error'), -100);
			}
		} else {
            $this->ajaxReturn($m->getError(), -1);
        }
	}

    /**
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-18
     * @desc    description
     */
    private function GetPlugConfig()
    {
        $data = [];
        foreach($_POST as $k=>$v)
        {
            if(substr($k, 0, 8) == 'plugins_')
            {
                $data[substr($k, 8)] = $v;
            }
        }
        return $data;
    }

	/**
     * [StatusUpdate 状态更新]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2017-01-12T22:23:06+0800
     */
    public function StatusUpdate()
    {
        if(!IS_AJAX)
        {
            $this->error(L('common_unauthorized_access'));
        }

        // 参数
        if(empty($_POST['id']) || !isset($_POST['state']))
        {
            $this->ajaxReturn(L('common_param_error'), -1);
        }

        // 数据更新
        if(M('Payment')->where(array('payment'=>I('id')))->save(array('is_enable'=>I('state'))))
        {
            $this->ajaxReturn(L('common_operation_edit_success'));
        } else {
            $this->ajaxReturn(L('common_operation_edit_error'), -100);
        }
    }

    /**
     * 安装
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     */
    public function Install()
    {
        if(!IS_AJAX)
        {
            $this->error(L('common_unauthorized_access'));
        }

        // 参数
        if(empty($_POST['id']))
        {
            $this->ajaxReturn(L('common_param_error'), -1);
        }

        // 数据处理
        $payment = I('id');
        $config = $this->GetPaymentConfig($payment);
        if($config !== false)
        {
            $data = $this->DataAnalysis($config);
            $data['payment'] = $payment;
            $data['element'] = empty($data['element']) ? '' : json_encode($data['element']);
            $data['apply_terminal'] = empty($data['apply_terminal']) ? '' : json_encode($data['apply_terminal']);
            $data['add_time'] = time();

            // 开始安装
            $m = D('Payment');
            if($m->create($data, 1))
            {
                if($m->add($data))
                {
                    $this->ajaxReturn(L('common_install_success'));
                } else {
                    $this->ajaxReturn(L('common_install_error'), -100);
                }
            } else {
                $this->ajaxReturn($m->getError(), -1);
            }
        } else {
            $this->ajaxReturn(L('common_plugins_config_error'), -10);
        }
    }

    /**
     * 卸载
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     */
    public function Uninstall()
    {
        if(!IS_AJAX)
        {
            $this->error(L('common_unauthorized_access'));
        }

        // 参数
        if(empty($_POST['id']))
        {
            $this->ajaxReturn(L('common_param_error'), -1);
        }

        // 开始卸载
        if(M('Payment')->where(['payment'=>I('id')])->delete())
        {
            $this->ajaxReturn(L('common_uninstall_success'));
        } else {
            $this->ajaxReturn(L('common_uninstall_error'), -100);
        }
    }

    /**
     * 删除插件
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     */
    public function Delete()
    {
        if(!IS_AJAX)
        {
            $this->error(L('common_unauthorized_access'));
        }

        // 参数
        if(empty($_POST['id']))
        {
            $this->ajaxReturn(L('common_param_error'), -1);
        }

        // 是否存在
        $file = $this->payment_dir.I('id').'.class.php';
        if(!file_exists($file))
        {
            $this->ajaxReturn(L('common_data_no_exist_error'), -2);
        }
        // 权限
        if(!is_writable($file))
        {
            $this->ajaxReturn(L('common_is_writable_error'), -3);
        }

        // 删除
        if(!@unlink($file))
        {
            $this->ajaxReturn(L('common_operation_delete_error'), -100);
        }

        $this->ajaxReturn(L('common_operation_delete_success'));
    }

    /**
     * 上传插件
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     */
    public function Upload()
    {
        // 是否ajax
        if(!IS_AJAX)
        {
            $this->error(L('common_unauthorized_access'));
        }

        // 文件上传校验
        $error = FileUploadError('file');
        if($error !== true)
        {
            $this->ajaxReturn($error, -1);
        }

        // 文件格式化校验
        $type = array('text/php');
        if(!in_array($_FILES['file']['type'], $type))
        {
            $this->ajaxReturn(L('payment_upload_format'), -2);
        }

        // 是否已有存在插件
        if(file_exists($this->payment_dir.$_FILES['file']['name']))
        {
            $this->ajaxReturn(L('common_plugins_already_existed_error'), -3);
        }

        // 存储文件
        if(!move_uploaded_file($_FILES['file']['tmp_name'], $this->payment_dir.$_FILES['file']['name']))
        {
            $this->ajaxReturn(L('common_upload_error'), -100);
        }

        // 文件校验
        $payment = htmlentities(str_replace('.class.php', '', $_FILES['file']['name']));
        $config = $this->GetPaymentConfig($payment);
        if($config === false)
        {
            @unlink($this->payment_dir.$_FILES['file']['name']);
            $this->ajaxReturn(L('payment_upload_error'), -10);
        }
        $this->ajaxReturn(L('common_upload_success'));
    }
}
?>