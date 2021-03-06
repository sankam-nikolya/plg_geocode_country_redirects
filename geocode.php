<?php
/**
 * @package     plg_geocode
 *
 * @copyright   Copyright (C) 2011 - 2016 SNAKAM, Inc. All rights reserved.
 * @license     GNU General Public License version 3 or later
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

class PlgSystemGeocode extends JPlugin
{
    private $languge;
    private $country;
    private $app;
    private $redirect = true;

    /**
     * Constructor.
     *
     * @param   object  &$subject  The object to observe.
     * @param   array   $config    An optional associative array of configuration settings.
     *
     * @since   1.5
     */
    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);

        $this->loadLanguage();

        $this->app = JFactory::getApplication();

        if ($this->app->isAdmin())
        {
            return;
        }

        $session = JFactory::getSession();

        $this->redirect = $session->get('redirect', true);
        
        if($this->redirect) {
            $this->redirect = boolval($this->app->input->cookie->get(
                JApplicationHelper::getHash('redirect'), 
                true
            ));
        }

        if(!$this->redirect) {
            $is_changed = boolval($this->app->input->get('ref', false, 'string'));
        } else {
            $is_changed = true;
        }

        if($is_changed ) {
            $this->redirect = false;
            $session->set('redirect', true);
            $cookie_expire = 0;
            $cookie_domain = $this->app->get('cookie_domain');
            $cookie_path   = $this->app->get('cookie_path', '/');
            $cookie_secure = $this->app->isSSLConnection();
            $this->app->input->cookie->set(
                JApplicationHelper::getHash('redirect'), 
                true, 
                $cookie_expire, 
                $cookie_path, 
                $cookie_domain, 
                $cookie_secure
            );
        }

        if(!$session->get('country', false) || !$session->get('default_language', false)) {
            $this->setDefaults();
        } else {
            $this->country = $session->get('country', false);
            $this->languge = $session->get('default_language', false);
        }

        $this->loadParams();
    }

    /**
     *
     * @return  void
     *
     * @since   2.5
     */
    public function onAfterRender()
    {
        if ($this->app->isAdmin())
        {
            return;
        }

        $show_404 = boolval($this->params->get('show_404', true));

        if($this->languge && $show_404) {
            $menu = $this->app->getMenu()->getActive();
            $language = $menu->language;
            if ($language != '*' && $language != $this->languge) {
                JError::raiseError(404, JText::_('JERROR_PAGE_NOT_FOUND'));
            }
        }
    }

    private function setDefaults() {
        $session = JFactory::getSession();

        $this->languge = JComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        $session->set('default_language', $this->languge);
        
        $this->country = $this->getUserCountry();
        $session->set('country', $this->country);
    }


    public function getUserCountry($ip = null)
    {
        if(empty($ip)) {
            $ip = $this->getUserIP();
        }

        if($ip) {
            $ip = ip2long($ip);

            $db = JFactory::getDbo();
            $query = $db->getQuery(true);

            $query->select(
                $db->quoteName(
                    'l.country_name',
                    'name'
                )
            );
            $query->select(
                $db->quoteName(
                    'l.country_iso_code',
                    'iso_code'
                )
            );

            $query->from(
                $db->quoteName('#__blocks_ipv4', 'b')
            );
            $query->join(
                'LEFT',
                $db->quoteName('#__location', 'l') . ' ON (' . $db->quoteName('b.geoname_id') . ' = ' . $db->quoteName('l.id') . ')'
            );
            $query->where(
                $db->quote($ip).' BETWEEN ' . $db->quoteName('network_start') . ' AND ' . $db->quoteName('network_last')
            );
            $query->setLimit(1);

            $db->setQuery($query);
            $result = $db->loadObject();

            if(!empty($result)) {
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
        
    }

    private function getUserIP()
    {
        $ip = false;
            
        if (isset($_SERVER)) {
            if ($_SERVER['HTTP_CLIENT_IP']) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else if(isset($_SERVER['HTTP_X_FORWARDED'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED'];
            } else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_FORWARDED_FOR'];
            } else if(isset($_SERVER['HTTP_FORWARDED'])) {
                $ip = $_SERVER['HTTP_FORWARDED'];
            } else if(isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv('HTTP_CLIENT_IP')) {
                $ip = getenv('HTTP_CLIENT_IP');
            } else if(getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } else if(getenv('HTTP_X_FORWARDED')) {
                $ip = getenv('HTTP_X_FORWARDED');
            } else if(getenv('HTTP_FORWARDED_FOR')) {
                $ip = getenv('HTTP_FORWARDED_FOR');
            } else if(getenv('HTTP_FORWARDED')) {
                $ip = getenv('HTTP_FORWARDED');
            } else if(getenv('REMOTE_ADDR')) {
                $ip = getenv('REMOTE_ADDR');
            }
        }
        
        return $ip;
    }

    private function loadParams()
    {
        $session = JFactory::getSession();

        $rules = $this->params->get('rules', array());
        $languages = $this->params->get('languages', array());

        $current_site = JUri::getInstance()->toString(array('host'));

        if(!empty($rules) && !empty($this->country)) {
            foreach ($rules as $rule) {
                if($current_site != JUri::getInstance($rule->domain)->toString(array('host'))) {
                    if($rule->country == $this->country->iso_code && $this->redirect) {
                        $this->app->redirect($rule->domain);
                    }
                }
            }
        }

        if(!empty($languages)) {
            foreach ($languages as $language) {
                if($current_site == JUri::getInstance($language->domain)->toString(array('host'))) {
                    $this->languge = $language->language;
                    
                    $session->set('default_language', $this->languge);
                    
                    JComponentHelper::getParams('com_languages')->set('site', $this->languge);

                    // Create a cookie.
                    $cookie_expire = 0;
                    $cookie_domain = $this->app->get('cookie_domain');
                    $cookie_path   = $this->app->get('cookie_path', '/');
                    $cookie_secure = $this->app->isSSLConnection();
                    $this->app->input->cookie->set(
                        JApplicationHelper::getHash('language'), 
                        $this->languge, 
                        $cookie_expire, 
                        $cookie_path, 
                        $cookie_domain, 
                        $cookie_secure
                    );
                }
            }
        }

        return;
    }

}