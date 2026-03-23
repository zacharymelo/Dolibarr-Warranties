<?php
/* Copyright (C) 2026 DPG Supply
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modWarrantySvc.class.php
 * \ingroup warrantysvc
 * \brief   Description and activation file for module SvcRequest
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module SvcRequest (RMA & Warranty Management)
 */
class modWarrantySvc extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Module identifier — must be unique globally.
		// Current value (510000) is valid for private/internal use (>500000 range).
		// TODO (DoliStore pre-publication): reserve a block in the 100000–499999 range at
		// https://wiki.dolibarr.org/index.php?title=List_of_modules_id
		// then update this value to your registered ID before submitting to DoliStore.
		$this->numero = 510000;

		// Family: crm, financial, hr, projects, products, ecm, technic, interface, other
		$this->family = "crm";
		$this->module_position = '50';

		// Module name (no spaces), used if translation string 'ModuleXXXName' not found
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "RMA (Return Merchandise Authorization) and Warranty Management for serialized equipment";
		$this->version = '1.18.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'technic';

		// Module parts (triggers, login, substitutions, menus, tpl, hooks, modulebuilder, cronjobs, unittest)
		$this->module_parts = array(
			'triggers' => 1,  // triggers/ directory enabled
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'hooks' => array('data' => array('elementproperties', 'productcard', 'commonobject'), 'entity' => '0'),
			'apis' => 1,      // api/ directory enabled (registers via Luracast)
		);

		// Data dirs created when module enabled
		$this->dirs = array(
			"/warrantysvc/temp",
		);

		// Config page
		$this->config_page_url = array("setup.php@warrantysvc");

		// Dependencies
		$this->hidden = false;
		$this->depends = array('modSociete', 'modProduct', 'modStock');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("warrantysvc@warrantysvc");
		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants saved in llx_const
		$this->const = array();

		// New pages on existing object tabs
		$this->tabs = array();
		$this->tabs[] = array('data' => 'project:+warrantysvc_svcrequest:ServiceRequests,technic,/warrantysvc/class/svcrequest.class.php,countForProject:warrantysvc@warrantysvc:$user->hasRight(\'warrantysvc\', \'svcrequest\', \'read\'):/warrantysvc/list.php?projectid=__ID__');

		// Dictionaries
		$this->dictionaries = array();

		// Boxes / Widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array(
			0 => array(
				'label'         => 'CheckOverdueRMAReturns',
				'jobtype'       => 'method',
				'class'         => '/warrantysvc/class/svcrequest.class.php',
				'objectname'    => 'SvcRequest',
				'method'        => 'checkOverdueReturns',
				'parameters'    => '',
				'comment'       => 'Check for overdue RMA unit returns and send reminders',
				'frequency'     => 1,
				'unitfrequency' => 86400,
				'status'        => 0,
				'test'          => 'isModEnabled("warrantysvc")',
				'priority'      => 50,
			),
		);

		// Permissions defined below
		$this->rights = array();
		$this->rights_class = 'warrantysvc';

		$r = 0;

		// Service Request permissions — object = 'svcrequest'
		$r++;
		$this->rights[$r][0] = 510001;
		$this->rights[$r][1] = 'Read service requests';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcrequest';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 510002;
		$this->rights[$r][1] = 'Create and edit service requests';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcrequest';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = 510003;
		$this->rights[$r][1] = 'Delete service requests';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcrequest';
		$this->rights[$r][5] = 'delete';

		$r++;
		$this->rights[$r][0] = 510004;
		$this->rights[$r][1] = 'Validate service requests';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcrequest';
		$this->rights[$r][5] = 'validate';

		$r++;
		$this->rights[$r][0] = 510005;
		$this->rights[$r][1] = 'Close service requests';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcrequest';
		$this->rights[$r][5] = 'close';

		// Warranty permissions
		$r++;
		$this->rights[$r][0] = 510011;
		$this->rights[$r][1] = 'Read warranties';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcwarranty';
		$this->rights[$r][5] = 'read';

		$r++;
		$this->rights[$r][0] = 510012;
		$this->rights[$r][1] = 'Create and edit warranties';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcwarranty';
		$this->rights[$r][5] = 'write';

		$r++;
		$this->rights[$r][0] = 510013;
		$this->rights[$r][1] = 'Delete warranties';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'svcwarranty';
		$this->rights[$r][5] = 'delete';

		// Main menu entries
		$this->menu = array();
		$r = 0;

		// Top-level menu entry
		$this->menu[$r] = array(
			'fk_menu'  => 0,
			'type'     => 'top',
			'titre'    => 'WarrantySvc',
			'prefix'   => img_picto('', $this->picto, 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'warrantysvc',
			'leftmenu' => '',
			'url'      => '/warrantysvc/list.php',
			'langs'    => 'warrantysvc@warrantysvc',
			'position' => 100,
			'enabled'  => 'isModEnabled("warrantysvc")',
			'perms'    => '$user->hasRight("warrantysvc", "svcrequest", "read")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Service Requests sub-menu
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=warrantysvc',
			'type'     => 'left',
			'titre'    => 'SvcRequests',
			'prefix'   => img_picto('', 'technic', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'warrantysvc',
			'leftmenu' => 'warrantysvc_list',
			'url'      => '/warrantysvc/list.php',
			'langs'    => 'warrantysvc@warrantysvc',
			'position' => 100,
			'enabled'  => 'isModEnabled("warrantysvc")',
			'perms'    => '$user->hasRight("warrantysvc", "svcrequest", "read")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// New Service Request
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=warrantysvc,fk_leftmenu=warrantysvc_list',
			'type'     => 'left',
			'titre'    => 'NewSvcRequest',
			'prefix'   => img_picto('', 'add', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'warrantysvc',
			'leftmenu' => 'warrantysvc_new',
			'url'      => '/warrantysvc/card.php?action=create',
			'langs'    => 'warrantysvc@warrantysvc',
			'position' => 110,
			'enabled'  => 'isModEnabled("warrantysvc")',
			'perms'    => '$user->hasRight("warrantysvc", "svcrequest", "write")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Warranties sub-menu
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=warrantysvc',
			'type'     => 'left',
			'titre'    => 'Warranties',
			'prefix'   => img_picto('', 'bill', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'warrantysvc',
			'leftmenu' => 'warrantysvc_warranty_list',
			'url'      => '/warrantysvc/warranty_list.php',
			'langs'    => 'warrantysvc@warrantysvc',
			'position' => 200,
			'enabled'  => 'isModEnabled("warrantysvc")',
			'perms'    => '$user->hasRight("warrantysvc", "svcwarranty", "read")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// New Warranty
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=warrantysvc,fk_leftmenu=warrantysvc_warranty_list',
			'type'     => 'left',
			'titre'    => 'NewWarranty',
			'prefix'   => img_picto('', 'add', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'warrantysvc',
			'leftmenu' => 'warrantysvc_warranty_new',
			'url'      => '/warrantysvc/warranty_card.php?action=create',
			'langs'    => 'warrantysvc@warrantysvc',
			'position' => 210,
			'enabled'  => 'isModEnabled("warrantysvc")',
			'perms'    => '$user->hasRight("warrantysvc", "svcwarranty", "write")',
			'target'   => '',
			'user'     => 0,
		);
		$r++;

		// Warranty Types management
		$this->menu[$r] = array(
			'fk_menu'  => 'fk_mainmenu=warrantysvc,fk_leftmenu=warrantysvc_warranty_list',
			'type'     => 'left',
			'titre'    => 'WarrantyTypes',
			'prefix'   => img_picto('', 'setup', 'class="paddingright pictofixedwidth"'),
			'mainmenu' => 'warrantysvc',
			'leftmenu' => 'warrantysvc_warranty_types',
			'url'      => '/warrantysvc/warranty_type_list.php',
			'langs'    => 'warrantysvc@warrantysvc',
			'position' => 220,
			'enabled'  => 'isModEnabled("warrantysvc")',
			'perms'    => '$user->hasRight("warrantysvc", "svcwarranty", "read")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 * Loads SQL tables from sql/ directory using standard Dolibarr mechanism.
	 *
	 * @param  string $options Options
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/warrantysvc/sql/');
		if ($result < 0) {
			return -1;
		}
		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Options
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
