<?php

/**
 * Repository Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

class Repository
{
    public $use;

    public $name;
    public $baseurl;
    public $description;
	
	public $label;

    public $protocol;
    public $host;
    public $path;

    public $authenticated;
    public $login;
    public $password;
    public $default;

    private $context;

    private $contenturl;

    public $url;
	public $displayUrl;

    public $errorMessage = '';

    public $isValid;
    public $needAuth;

    public function __construct($xml, $context = null, $opts = array())
    {
        $this->use = $xml->getAttribute('use');

        if ($this->use != '')
        {

            $wiff = WIFF::getInstance();
            $xml = new DOMDocument();
            $xml->load($wiff->params_filepath);
            if ($xml === false)
            {
                $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->contexts_filepath);
                return false;
            }

            $xpath = new DOMXPath($xml);

            // Get repository with this name from WIFF repositories
            $wiffRepoList = $xpath->query("/wiff/repositories/access[@name='".$this->use ."']");
            if ($wiffRepoList->length == 0)
            {
                // If there is no repository with such name
                $this->errorMessage = "Repository ".$this->use ." does not exist.";
                return false;
            } else if ($wiffRepoList->length > 1)
            {
                // If there is more than one repository with such name
                $this->errorMessage = "More than one repository with name ".$this->use .".";
                return false;
            }

            $repository = $wiffRepoList->item(0);

            $this->name = $repository->getAttribute('name');

            $this->baseurl = $repository->getAttribute('baseurl');
            $this->description = $repository->getAttribute('description');

            if ($this->baseurl == '')
            {
                $this->protocol = $repository->getAttribute('protocol');
                $this->host = $repository->getAttribute('host');
                $this->path = $repository->getAttribute('path');
                // Handle frenglish "authentified" (bug #1681)
                if( $repository->hasAttribute('authentified') ) {
                	$this->authenticated = $repository->getAttribute('authentified');
                }
                if( $repository->hasAttribute('authenticated') ) {
                	$this->authenticated = $repository->getAttribute('authenticated');
                }
                $this->login = $repository->getAttribute('login');
                $this->password = $repository->getAttribute('password');
		$this->default = $repository->getAttribute('default');
            }

            if ($this->authenticated)
            {
                $info = $wiff->getAuthInfo($this->name);
				//echo 'INFO '.print_r($info,true);
                if ($info)
                {
                	//echo 'I have login info for '.$this->name;
                    if (!$this->login)
                    {
                        $this->login = $info->login;
                    }
                    $this->password = $info->password;
                }
            }

        } else
        {

            $this->name = $xml->getAttribute('name');

            $this->baseurl = $xml->getAttribute('baseurl');
            $this->description = $xml->getAttribute('description');

            if ($this->baseurl == '')
            {
                $this->protocol = $xml->getAttribute('protocol');
                $this->host = $xml->getAttribute('host');
                $this->path = $xml->getAttribute('path');
                // Handle frenglish "authentified" (bug #1681)
                if( $xml->hasAttribute('authentified') ) {
                	$this->authenticated = $xml->getAttribute('authentified');
                }
                if( $xml->hasAttribute('authenticated') ) {
                	$this->authenticated = $xml->getAttribute('authenticated');
                }
                $this->login = $xml->getAttribute('login');
                $this->password = $xml->getAttribute('password');
		$this->default = $xml->getAttribute('default');
            }

        }

        $this->contenturl = $this->getUrl().'/content.xml';
        $this->context = $context;

        // Evaluate if repo is valid and need authentification
	if( array_key_exists('checkValidity', $opts) && $opts['checkValidity'] === true ) {
	  $this->isValid();
	}
//        $this->needAuth();

    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function authentify($login, $password)
    {
        if (!$this->login)
        {
            $this->login = $login;
        }
        $this->password = $password;
        $this->contenturl = $this->getUrl().'/content.xml';
        return $this->isValid();
    }

    public function getUrl()
    {
        if ($this->baseurl)
        {
            $this->url = $this->baseurl;
			$this->displayUrl = $this->url;
        } elseif ($this->authenticated && $this->login && $this->password)
        {
            $this->url = $this->protocol.'://'.$this->login.':'.$this->password.'@'.$this->host.'/'.$this->path;
        } else
        {
            $this->url = $this->protocol.'://'.$this->host.'/'.$this->path;
        }
		if($this->authenticated == 'yes')
		{
			$this->displayUrl = $this->protocol.'://*******:*******@'.$this->host.'/'.$this->path;
		} else {
			$this->displayUrl = $this->protocol.'://'.$this->host.'/'.$this->path;
		}
        return $this->url;
    }

    /**
     * Return true if repository has a content.xml file
     * @return
     */
    public function isValid()
    {
        require_once ('class/Class.WIFF.php');
        require_once ('class/Class.Module.php');

        $wiff = WIFF::getInstance();
        $tmpfile = $wiff->downloadUrl($this->contenturl);
        if ($tmpfile === false)
        {
            //$this->errorMessage = $wiff->errorMessage;
            // Silence wiff error generated by downloadUrl() since it is not unexpected here that file is not downloadable and code calling this method should handle properly the false return.
            $wiff->errorMessage = '';
            $this->isValid = false;
            return false;
        }

        $xml = new DOMDocument();
        $ret = $xml->load($tmpfile);
        if ($ret === false)
        {
            unlink($tmpfile);
            //$this->errorMessage = sprintf("Error loading XML file '%s'.", $tmpfile);
            $this->isValid = false;
            return false;
        }

		$this->label = $xml->documentElement->getAttribute('label');
        $this->isValid = true;
        return true;
    }

    /**
     * Return true if repository needs authentification
     * @return
     */
    public function needAuth()
    {
        if ($this->authenticated == 'yes' && !$this->password)
        {
            $this->needAuth = true;
            return true;
        }
        $this->needAuth = false;
        return false;
    }

    /**
     * Get Module list (available modules on repository)
     * @return array of object Module
     */
    public function getModuleList()
    {
        require_once ('class/Class.WIFF.php');
        require_once ('class/Class.Module.php');

        $wiff = WIFF::getInstance();
        $tmpfile = $wiff->downloadUrl($this->contenturl);
        if ($tmpfile === false)
        {
            $this->errorMessage = $wiff->errorMessage;
            return false;
        }

        $xml = new DOMDocument();
        $ret = $xml->load($tmpfile);
        if ($ret === false)
        {
            unlink($tmpfile);
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $tmpfile);
            return false;
        }

        $xpath = new DOMXPath($xml);

        $modules = $xpath->query("/repo/modules/module");

        $moduleList = array ();
        foreach ($modules as $module)
        {
            $moduleList[] = new Module($context, $this, $module, false);
        }

        unlink($tmpfile);
        return $moduleList;
    }

}

?>
