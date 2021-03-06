<?php

/**
 * @file controllers/grid/pubIds/PubIdExportRepresentationsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PubIdExportRepresentationsListGridHandler
 * @ingroup controllers_grid_pubIds
 *
 * @brief Handle exportable representations with pub ids list grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('controllers.grid.pubIds.PubIdExportRepresentationsListGridCellProvider');

class PubIdExportRepresentationsListGridHandler extends GridHandler {
	/** @var ImportExportPlugin */
	var $_plugin;

	/**
	 * Constructor
	 */
	function PubIdExportRepresentationsListGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow')
		);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		$context = $request->getContext();

		// Basic grid configuration.
		$this->setTitle('plugins.importexport.common.export.articles');

		// Load submission-specific translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_SUBMISSION, // title filter
			LOCALE_COMPONENT_PKP_SUBMISSION, // authors filter
			LOCALE_COMPONENT_APP_MANAGER
		);

		$pluginCategory = $request->getUserVar('category');
		$pluginPathName = $request->getUserVar('plugin');
		$this->_plugin = PluginRegistry::loadPlugin($pluginCategory, $pluginPathName);
		assert(isset($this->_plugin));

		// Fetch the authorized roles.
		$authorizedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		// Grid columns.
		$cellProvider = new PubIdExportRepresentationsListGridCellProvider($this->_plugin, $authorizedRoles);
		$this->addColumn(
			new GridColumn(
				'id',
				null,
				__('common.id'),
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 10)
			)
		);
		$this->addColumn(
			new GridColumn(
				'title',
				'grid.submission.itemTitle',
				null,
				null,
				$cellProvider,
				array('html' => true,
						'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'issue',
				'issue.issue',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
					'width' => 20)
			)
		);
		$this->addColumn(
			new GridColumn(
				'galley',
				'submission.layout.galleyLabel',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
					'width' => 20)
			)
		);
		$this->addColumn(
			new GridColumn(
				'pubId',
				null,
				$this->_plugin->getPubIdDisplayType(),
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 15)
			)
		);
		$this->addColumn(
			new GridColumn(
				'status',
				'common.status',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 10)
			)
		);

	}


	//
	// Implemented methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.selectableItems.SelectableItemsFeature');
		import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
		return array(new SelectableItemsFeature(), new PagingFeature());
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		return array_merge(parent::getRequestArgs(), array('category' => $this->_plugin->getCategory(), 'plugin' => basename($this->_plugin->getPluginPath())));
	}

	/**
	 * @copydoc GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		return false; // Nothing is selected by default
	}

	/**
	 * @copydoc GridHandler::getSelectName()
	 */
	function getSelectName() {
		return 'selectedRepresentations';
	}

	/**
	 * @copydoc GridHandler::getFilterForm()
	 */
	protected function getFilterForm() {
		return 'controllers/grid/pubIds/pubIdExportRepresentationsGridFilter.tpl';
	}

	/**
	 * @copydoc GridHandler::renderFilter()
	 */
	function renderFilter($request, $filterData = array()) {
		$context = $request->getContext();
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issuesIterator = $issueDao->getPublishedIssues($context->getId());
		$issues = $issuesIterator->toArray();
		foreach ($issues as $issue) {
			$issueOptions[$issue->getId()] = $issue->getIssueIdentification();
		}
		$issueOptions[0] = __('plugins.importexport.common.filter.issue');
		ksort($issueOptions);
		$statusNames = $this->_plugin->getStatusNames();
		$filterColumns = $this->getFilterColumns();
		$allFilterData = array_merge(
			$filterData,
			array(
				'columns' => $filterColumns,
				'issues' => $issueOptions,
				'status' => $statusNames,
				'gridId' => $this->getId(),
			));
		return parent::renderFilter($request, $allFilterData);
	}

	/**
	 * @copydoc GridHandler::getFilterSelectionData()
	 */
	function getFilterSelectionData($request) {
		$search = (string) $request->getUserVar('search');
		$column = (string) $request->getUserVar('column');
		$issueId = (int) $request->getUserVar('issueId');
		$statusId = (string) $request->getUserVar('statusId');
		return array(
			'search' => $search,
			'column' => $column,
			'issueId' => $issueId,
			'statusId' => $statusId,
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$context = $request->getContext();
		list($search, $column, $issueId, $statusId) = $this->getFilterValues($filter);
		$title = $author = null;
		if ($column == 'title') {
			$title = $search;
		} elseif ($column == 'author') {
			$author = $search;
		}
		$pubIdStatusSettingName = null;
		if ($statusId) {
			$pubIdStatusSettingName = $this->_plugin->getDepositStatusSettingName();
		}
		return $articleGalleyDao->getByPubIdType(
			$this->_plugin->getPubIdType(),
			$context?$context->getId():null,
			$title,
			$author,
			$issueId,
			$pubIdStatusSettingName,
			$statusId,
			$this->getGridRangeInfo($request, $this->getId())
		);
	}


	//
	// Own protected methods
	//
	/**
	 * Get which columns can be used by users to filter data.
	 * @return array
	 */
	protected function getFilterColumns() {
		return array(
			'title' => __('submission.title'),
			'author' => __('submission.authors')
		);
	}

	/**
	 * Process filter values, assigning default ones if
	 * none was set.
	 * @return array
	 */
	protected function getFilterValues($filter) {
		if (isset($filter['search']) && $filter['search']) {
			$search = $filter['search'];
		} else {
			$search = null;
		}
		if (isset($filter['column']) && $filter['column']) {
			$column = $filter['column'];
		} else {
			$column = null;
		}
		if (isset($filter['issueId']) && $filter['issueId']) {
			$issueId = $filter['issueId'];
		} else {
			$issueId = null;
		}
		if (isset($filter['statusId']) && $filter['statusId'] != DOI_EXPORT_STATUS_ANY) {
			$statusId = $filter['statusId'];
		} else {
			$statusId = null;
		}
		return array($search, $column, $issueId, $statusId);
	}

}

?>
