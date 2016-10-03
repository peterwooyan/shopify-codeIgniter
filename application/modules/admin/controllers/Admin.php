<?php

/*
 * @Author:    Kiril Kirkov
 *  Gitgub:    https://github.com/kirilkirkov
 */
if (!defined('BASEPATH'))
    exit('No direct script access allowed');
date_default_timezone_set('Europe/Sofia');

class Admin extends MX_Controller {

    private $num_rows = 10;
    private $thumb_width = 300;
    private $thumb_height = 300;
    private $username;
    private $history;
    private $def_lang;
	private $activePages;

    //$data['links_pagination'] = pagination('admin/view_all', $rowscount, $this->num_rows, 3);

    public function __construct() {
        parent::__construct();
        $this->history = $this->config->item('admin_history');
        $this->load->library(array('session', 'form_validation'));
        $this->load->helper(array('text', 'file', 'pagination', 'text', 'except_letters', 'currencies'));
        $this->load->Model('Admin_model');
        $this->def_lang = $this->config->item('language_abbr');
		$this->activePages = $vars['activePages'] = $this->getActivePages();
    	$this->load->vars($vars); 
    }


    public function index() {
        $data = array();
        $head = array();
        $head['title'] = 'Administration';
        $head['description'] = '!';
        $head['keywords'] = '';
        $this->load->view('_parts/header', $head);
        if ($this->session->userdata('logged_in')) {
            //$this->load->view('home_adm', $data);
            $this->username = $this->session->userdata('logged_in');
            redirect('admin/publish');
        } else {
            $this->form_validation->set_rules('username', 'Username', 'trim|required');
            $this->form_validation->set_rules('password', 'Password', 'trim|required');
            if ($this->form_validation->run($this)) {
                $result = $this->Admin_model->loginCheck($_POST);
                if (!empty($result)) {
                    $this->session->set_userdata('logged_in', $result['username']);
                    $this->username = $this->session->userdata('logged_in');
                    $this->saveHistory('User ' . $result['username'] . ' logged in');
                    redirect('admin/publish');
                } else {
                    $this->saveHistory('Cant login with - User:' . $_POST['username'] . ' and Pass:' . $_POST['username']);
                    $this->session->set_flashdata('err_login', 'Wrong username or password!');
                }
            }
            $this->load->view('login');
        }
        $this->load->view('_parts/footer');
    }

    public function publish($id = 0) {
        $this->login_check();
        $is_update = false;
        $trans_load = null;
        if ($id > 0 && $_POST == null) {
            $_POST = $this->Admin_model->getOneproduct($id);
            $trans_load = $this->Admin_model->getTranslations($id, 'product');
        }
       if (isset($_POST['submit'])) {
       	if($id > 0) $is_update = true;
       	unset($_POST['submit']); 
        	$u_path = 'shop_images/';
            $config['upload_path'] = './attachments/'.$u_path;
            $config['allowed_types'] = 'gif|jpg|png|jpeg|JPG|PNG|JPEG';
            $this->load->library('upload', $config);
            $this->upload->initialize($config);
            if (!$this->upload->do_upload('userfile')) {
                log_message('error', 'Image Upload Error: ' . $this->upload->display_errors());
            }
            $img = $this->upload->data();
            if ($img['file_name'] != null) {
                $_POST['image'] = $img['file_name'];
            }
            if (isset($_GET['to_lang'])) {
                $id = 0;
            }
            $translations = array(
            		'abbr' => $_POST['translations'],
            		'title' => $_POST['title'],
            		'basic_description' => $_POST['basic_description'],
            		'description' => $_POST['description'],
            		'price' => $_POST['price'],
					'old_price' => $_POST['old_price']
            );
			$flipped = array_flip($_POST['translations']);
			$_POST['title_for_url'] = $_POST['title'][$flipped[$this->def_lang]];
            unset($_POST['translations'], $_POST['title'], $_POST['basic_description'], $_POST['description'], $_POST['price'], $_POST['old_price']); //remove for product
            $result = $this->Admin_model->setProduct($_POST, $id); 
            if ($result !== false) {
            	$this->Admin_model->setProductTranslation($translations, $result, $is_update); // send to translation table
                $this->session->set_flashdata('result_publish', 'product is published!');
                if ($id == 0) {
                    $this->saveHistory('Success published product');
                } else {
                    $this->saveHistory('Success updated product');
                }
               redirect('admin/products'); 
            } else {
                $this->session->set_flashdata('result_publish', 'Problem with product publish!');
            }
        }
        $data = array();
        $head = array();
        $head['title'] = 'Administration - Publish Product';
        $head['description'] = '!';
        $head['keywords'] = '';
        $data['id'] = $id;
        $data['trans_load'] = $trans_load;
        $data['languages'] = $this->Admin_model->getLanguages();
        $data['shop_categories'] = $this->Admin_model->getShopCategories();
        $this->load->view('_parts/header', $head);
        $this->load->view('publish', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to publish page');
    }

    public function products($page = 0) {
        $this->login_check();
        $this->saveHistory('Go to products');
        if (isset($_GET['delete'])) {
            $result = $this->Admin_model->deleteproduct($_GET['delete']);
            if ($result == true) {
                $this->session->set_flashdata('result_delete', 'product is deleted!');
                $this->saveHistory('Delete product id - ' . $_GET['delete']);
            } else {
                $this->session->set_flashdata('result_delete', 'Problem with product delete!');
            }
            redirect('admin/products');
        }
        $data = array();
        $head = array();
        $head['title'] = 'Administration - View products';
        $head['description'] = '!';
        $head['keywords'] = '';

        if ($this->input->get('search') !== NULL) {
            $search = $this->input->get('search');
            $this->saveHistory('Search for - ' . $search);
        } else {
            $search = null;
        }
        if ($this->input->get('orderby') !== NULL) {
            $orderby = $this->input->get('orderby');
        } else {
            $orderby = null;
        }
        $data['products_lang'] = $products_lang = $this->session->userdata('admin_lang_products');
        $rowscount = $this->Admin_model->productsCount($search);
        $data['products'] = $this->Admin_model->getproducts($this->num_rows, $page, $search, $orderby);
        $data['links_pagination'] = pagination('admin/products', $rowscount, $this->num_rows, 3);
        $data['num_shop_art'] = $this->Admin_model->numShopproducts();
        $data['languages'] = $this->Admin_model->getLanguages();

        $this->load->view('_parts/header', $head);
        $this->load->view('products', $data);
        $this->load->view('_parts/footer');
    }
    
    public function convertCurrency() {
    	if ($this->input->is_ajax_request()) {
    		$amount=$_POST['sum'];
    		$from=$_POST['from'];
    		$to=$_POST['to'];
		    $url = "https://www.google.com/finance/converter?a=$amount&from=$from&to=$to";
		    $data = file_get_contents($url);
		    preg_match("/<span class=bld>(.*)<\/span>/", $data, $converted);
		    $converted = preg_replace("/[^0-9.]/", "", $converted[1]);
			$this->saveHistory('Convert currency from '.$from.' to '.$to.' with amount '.$amount);
		    echo round($converted, 2);
    	}
	}
    
    public function shop_categories() {
    	$this->login_check();
    	$data = array();
    	$head = array();
    	$head['title'] = 'Administration - Home Categories';
    	$head['description'] = '!';
    	$head['keywords'] = '';
    	$data['shop_categories'] = $this->Admin_model->getShopCategories();
    	$data['languages'] = $this->Admin_model->getLanguages();
    	if (isset($_GET['delete'])) {
			$this->saveHistory('Delete a shop categorie');
    		$result = $this->Admin_model->deleteShopCategorie($_GET['delete']);
    		if ($result == true) {
    			$this->saveHistory('Home Categorie id - ' . $_GET['delete']);
    			$this->session->set_flashdata('result_delete', 'Shop Categorie is deleted!');
    		} else {
    			$this->session->set_flashdata('result_delete', 'Problem with Shop Categorie delete!');
    		}
    		redirect('admin/shop_categories');
    	}
    	if (isset($_POST['submit'])) {
			$this->saveHistory('Add a shop categorie');
    		$result = $this->Admin_model->setShopCategorie($_POST);
    		if ($result === true) {
    			$this->session->set_flashdata('result_add', 'shop categorie is added!');
    			$this->saveHistory('Added shop categorie');
    		} else {
    			$this->session->set_flashdata('result_add', 'Problem with Shop categorie add!');
    		}
    		redirect('admin/shop_categories');
    	}
    	 
    	$this->load->view('_parts/header', $head);
    	$this->load->view('shop_categories', $data);
    	$this->load->view('_parts/footer');
    	$this->saveHistory('Go to shop categories');
    }

    public function languages() {
        $this->login_check();
        if (isset($_GET['delete'])) {
            $result = $this->Admin_model->deleteLanguage($_GET['delete']);
            if ($result == true) {
                $this->saveHistory('Delete language id - ' . $_GET['delete']);
                $this->session->set_flashdata('result_delete', 'Language is deleted!');
            } else {
                $this->session->set_flashdata('result_delete', 'Problem with language delete!');
            }
            redirect('admin/languages');
        }
        $data = array();
        $head = array();
        $head['title'] = 'Administration - Languages';
        $head['description'] = '!';
        $data['def_lang'] =  $this->def_lang;
        $head['keywords'] = '';
        $data['languages'] = $this->Admin_model->getLanguages();

        $this->form_validation->set_rules('abbr', 'Abbrevation', 'trim|required|is_unique[languages.abbr]');
        if ($this->form_validation->run($this)) {
        	$config['upload_path']  = './attachments/lang_flags/';
        	$config['allowed_types'] = 'gif|jpg|png';
        	$this->load->library('upload', $config);
        	if (!$this->upload->do_upload('userfile')) {
        		$error = $this->upload->display_errors();
        		log_message('error', 'Language image upload error: ' . $error);
        	} else {
        		$img = $this->upload->data();
        		if ($img['file_name'] != null) $_POST['flag'] = $img['file_name'];
        	}
            $result = $this->Admin_model->setLanguage($_POST);
            if ($result === true) {
                $this->session->set_flashdata('result_add', 'Language is added!');
                $this->saveHistory('Create language - ' . $_POST['abbr']);
            } else {
                $this->session->set_flashdata('result_add', 'Problem with language add!');
            }
            redirect('admin/languages');
        }

        $this->load->view('_parts/header', $head);
        $this->load->view('languages', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to languages');
    }
	
    public function users() {
        $this->login_check();
        if (isset($_GET['delete'])) {
            $result = $this->Admin_model->deleteUser($_GET['delete']);
            if ($result == true) {
                $this->saveHistory('Delete user id - ' . $_GET['delete']);
                $this->session->set_flashdata('result_delete', 'User is deleted!');
            } else {
                $this->session->set_flashdata('result_delete', 'Problem with user delete!');
            }
            redirect('admin/users');
        }
		if (isset($_GET['edit']) && !isset($_POST['username'])) {
            $_POST = $this->Admin_model->getUsers($_GET['edit']);
        }
        $data = array();
        $head = array();
        $head['title'] = 'Administration - Users';
        $head['description'] = '!';
        $data['def_lang'] =  $this->def_lang;
        $head['keywords'] = '';
        $data['users'] = $this->Admin_model->getUsers();
        $this->form_validation->set_rules('username', 'User', 'trim|required');
        if ($this->form_validation->run($this)) {
            $result = $this->Admin_model->setUser($_POST);
            if ($result === true) {
                $this->session->set_flashdata('result_add', 'User is added!');
                $this->saveHistory('Create user - ' . $_POST['username']);
            } else {
                $this->session->set_flashdata('result_add', 'Problem with user add!');
            }
            redirect('admin/users');
        }

        $this->load->view('_parts/header', $head);
        $this->load->view('users', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to users');
    }

    public function categories() {
        $this->login_check();
        if (isset($_GET['delete'])) {
            $result = $this->Admin_model->deleteCategorie($_GET['delete']);
            if ($result == true) {
                $this->saveHistory('Delete categorie id - ' . $_GET['delete']);
                $this->session->set_flashdata('result_delete', 'Categorie is deleted!');
            } else {
                $this->session->set_flashdata('result_delete', 'Problem with categorie delete!');
            }
            redirect('admin/categories');
        }
        $data = array();
        $head = array();
        $head['title'] = 'Administration - Categories';
        $head['description'] = '!';
        $head['keywords'] = '';
        $data['categoiries'] = $this->Admin_model->getCategories();

        if (!isset($_POST['id']) || $_POST['id'] == 0) {
            $this->form_validation->set_rules('name', 'Name', 'trim|required|is_unique[categories.name]');
        } else {
            $this->form_validation->set_rules('name', 'Name', 'trim|required');
        }
        if ($this->form_validation->run($this)) {
            $result = $this->Admin_model->setCategorie($_POST);
            if ($result === true) {
                $this->session->set_flashdata('result_add', 'Categorie is added!');
                $this->saveHistory('Create categorie - ' . $_POST['name']);
                redirect('admin/categories');
            } else {
                $this->session->set_flashdata('result_add', 'Problem with categorie add!');
            }
        }

        $this->load->view('_parts/header', $head);
        $this->load->view('categories', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to categories');
    }

    public function fileManager() {
        $this->login_check();
        $data = array();
        $head = array();
        $head['title'] = 'Administration - File Manager';
        $head['description'] = '!';
        $head['keywords'] = '';

        $this->load->view('_parts/header', $head);
        $this->load->view('filemanager', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to File Manager');
    }
	
	public function orders() {
        $this->login_check();
        $data = array();
        $head = array();
        $head['title'] = 'Administration - Orders';
        $head['description'] = '!';
        $head['keywords'] = '';

		$order_by = null;
		if(isset($_GET['order_by'])) {
			$order_by = $_GET['order_by'];
		}
		$data['cash_on_delivery'] = $this->Admin_model->getCashOnDeliveryOrders($order_by);
        $this->load->view('_parts/header', $head);
        $this->load->view('orders', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to orders page');
    }
    
    public function querybuilder() {
        $this->login_check();
        $data = array();
        $head = array();
        $head['title'] = 'Administration - QueryBuilder';
        $head['description'] = '!';
        $head['keywords'] = '';

        if (isset($_POST['query'])) {
            $this->saveHistory('Send query from querybuilder: ' . $_POST['query']);
        }

        $this->load->view('_parts/header', $head);
        $this->load->view('querybuilder', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to QueryBuilder Page');
    }

    public function plugins() {
        $this->login_check();
        $data = array();
        $head = array();
        $head['title'] = 'Administration - PLugins';
        $head['description'] = '!';
        $head['keywords'] = '';

        $this->load->view('_parts/header', $head);
        $this->load->view('plugins', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to Plugins');
    }

    public function history($page = 0) {
        $this->login_check();
        $data = array();
        $head = array();
        $head['title'] = 'Administration - History';
        $head['description'] = '!';
        $head['keywords'] = '';

        $rowscount = $this->Admin_model->historyCount();
        $data['actions'] = $this->Admin_model->getHistory($this->num_rows, $page);
        $data['links_pagination'] = pagination('admin/history', $rowscount, $this->num_rows, 3);
        $data['history'] = $this->history;

        $this->load->view('_parts/header', $head);
        $this->load->view('history', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to History');
    }

    private function saveHistory($activity) {
        if ($this->history === true) {
            $usr = $this->username;
            $this->Admin_model->setHistory($activity, $usr);
        }
    }

    private function createThumb() {
        $config['image_library'] = 'gd2';
        $config['source_image'] = './attachments/images/' . $this->upload->file_name;
        $config['new_image'] = './attachments/thumbs/' . $this->upload->file_name;
        $config['create_thumb'] = TRUE;
        $config['maintain_ratio'] = FALSE;
        $config['thumb_marker'] = '';
        $config['width'] = $this->thumb_width;
        $config['height'] = $this->thumb_height;

        $this->load->library('image_lib', $config);
        $this->image_lib->initialize($config);
        if (!$this->image_lib->resize()) {
            log_message('error', 'Thumb Upload Error: ' . $this->image_lib->display_errors());
        }
    }

    public function changePass() {  //called from ajax
        $this->login_check();
        $result = $this->Admin_model->changePass($_POST['new_pass'], $this->username);
        if ($result == true)
            echo 1;
        else
            echo 0;
        $this->saveHistory('Password change for user: ' . $this->username);
    }

    public function productstatusChange() { //called from ajax
        $this->login_check();
        $result = $this->Admin_model->productstatusChagne($_POST['id'], $_POST['to_status']);
        if ($result == true)
            echo 1;
        else
            echo 0;
        $this->saveHistory('Change product id ' . $_POST['id'] . ' to status ' . $_POST['to_status']);
    }
	
	public function changeOrderStatus() {
		$this->login_check();
		  $result = $this->Admin_model->changeOrderStatus($_POST['the_id'], $_POST['to_status']);
        if ($result == true)
            echo 1;
        else
            echo 0;
        $this->saveHistory('Change order status id ' . $_POST['the_id'] . ' to status ' . $_POST['to_status']);
	}

    public function logout() {
        $this->session->sess_destroy();
        redirect('admin');
    }

    private function login_check() {
        if (!$this->session->userdata('logged_in')) {
            redirect('admin');
        }
        $this->username = $this->session->userdata('logged_in');
    }
	
	public function blog($page = 0) {
		$this->login_check();
		if(isset($_GET['delete'])) {
			$this->Admin_model->deletePost($_GET['delete']);
			redirect('admin/blog');
		}
        $data = array();
        $head = array();
        $head['title'] = 'Administration - Blog Posts';
        $head['description'] = '!';
        $head['keywords'] = '';
		

    	if ($this->input->get('search') !== NULL) {
    		$search = $this->input->get('search');
    	} else {
    		$search = null;
    	}
    	$data = array();
    	$rowscount = $this->Admin_model->postsCount($search);
    	$data['posts'] = $this->Admin_model->getPosts(null, $this->num_rows, $page, $search);
    	$data['links_pagination'] = pagination('admin/blog', $rowscount, $this->num_rows, 3);
    	$data['page'] = $page;
		
        $this->load->view('_parts/header', $head);
        $this->load->view('blog', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to Blog');
	}
	
	public function blogPublish($id = 0) {
		$this->login_check();
		$trans_load = null;
		$is_update = false;
		if($id > 0) $is_update = true;
    	if ($id > 0 && $_POST == null) {
    		$_POST = $this->Admin_model->getOnePost($id);
			 $trans_load = $this->Admin_model->getTranslations($id, 'blog');
    	}
    	 if (isset($_POST['submit'])) {
			unset($_POST['submit']);
    		$config['upload_path'] = './attachments/blog_images/';
    		$config['allowed_types'] = 'gif|jpg|png|jpeg';
    		$this->load->library('upload', $config);
    		$this->upload->initialize($config);
    		if (!$this->upload->do_upload('userfile')) {
    			log_message('error', 'Image Upload Error: ' . $this->upload->display_errors());
    		}
    		$img = $this->upload->data();
    		if ($img['file_name'] != null) {
    			$_POST['image'] = $img['file_name'];
    		}
			$translations = array(
            		'abbr' => $_POST['translations'],
            		'title' => $_POST['title'],
            		'description' => $_POST['description']
            );
			
			$flipped = array_flip($_POST['translations']);
			$_POST['title'] = $_POST['title'][$flipped[$this->def_lang]];
			unset($_POST['description'], $_POST['translations']);
    		$result = $this->Admin_model->setPost($_POST, $id);
    		if ($result !== false) {
				$this->Admin_model->setBlogTranslations($translations, $result, $is_update);
    			$this->session->set_flashdata('result_publish', 'Successful published!');
    			redirect('admin/blog');
    		} else {
    			$this->session->set_flashdata('result_publish', 'Blog post publish error!');
    		}
    	}
		
        $data = array();
        $head = array();
		$data['id'] = $id;
        $head['title'] = 'Administration - Publish Blog Post';
        $head['description'] = '!';
        $head['keywords'] = '';
		$data['languages'] = $this->Admin_model->getLanguages();
		$data['trans_load'] = $trans_load;
        $this->load->view('_parts/header', $head);
        $this->load->view('blogPublish', $data);
        $this->load->view('_parts/footer');
        $this->saveHistory('Go to Blog Publish product');
	}
		
	public function getActivePages() {
		return $this->Admin_model->getPages(true, false);
	}
	
	
	public function pages() {
		$this->login_check();
		$data = array();
        $head = array();
		$head['title'] = 'Administration - Pages Manage';
		$head['description'] = '!';
        $head['keywords'] = '';
		$data['pages'] = $this->Admin_model->getPages(null, true);
		$this->load->view('_parts/header', $head);
        $this->load->view('pages', $data);
        $this->load->view('_parts/footer');
		$this->saveHistory('Go to Pages manage');
	}
	
	public function changePageStatus() {
		$this->login_check();
		$result = $this->Admin_model->changePageStatus($_POST['id'], $_POST['status']);
        if ($result == true)
            echo 1;
        else
            echo 0;
        $this->saveHistory('Page status Changed' );
	}

}
