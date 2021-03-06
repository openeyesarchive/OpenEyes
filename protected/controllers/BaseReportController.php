<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

class BaseReportController extends BaseController
{
	public $layout = '//layouts/reports';
	public $items_per_page = 30;
	public $form_errors;
	public $modulePathAlias;
	public $assetPathAlias;
	public $assetPath;

	public function accessRules()
	{
		return array(array('allow', 'roles' => array('admin')));
	}

	protected function beforeAction($action)
	{
		parent::beforeAction($action);

		Yii::app()->assetManager->registerCssFile('css/reports.css', null, 10);
		Yii::app()->assetManager->registerScriptFile('js/reports.js');

		if ($this->module) {
			$this->modulePathAlias = 'application.modules.'.$this->getModule()->name;
			$this->assetPathAlias = $this->modulePathAlias .'.assets';

			// Set asset path
			if (file_exists(Yii::getPathOfAlias($this->assetPathAlias))) {
				$this->assetPath = Yii::app()->assetManager->getPublishedPathOfAlias('application.modules.'.$this->getModule()->name.'.assets');
			}
		
			if (file_exists(getcwd() . '/protected/modules/' . $this->module->id . '/assets/js/reports.js')) {
				Yii::app()->clientScript->registerScriptFile("{$this->assetPath}/js/reports.js", CClientScript::POS_END);
			}
		} else {
			if (file_exists(getcwd() . '/protected/assets/js/report_' . $action->id . '.js')) {
				Yii::app()->assetManager->registerScriptFile('js/report_' . $action->id . '.js');
			}
		}

		$this->jsVars['items_per_page'] = $this->items_per_page;

		return true;
	}

	protected function initPagination($model, $criteria = null)
	{
		$criteria = is_null($criteria) ? new CDbCriteria() : $criteria;
		$itemsCount = $model->count($criteria);
		$pagination = new CPagination($itemsCount);
		$pagination->pageSize = $this->items_per_page;
		$pagination->applyLimit($criteria);
		return $pagination;
	}

	protected function sendCsvHeaders($filename)
	{
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=$filename");
		header("Pragma: no-cache");
		header("Expires: 0");
	}

	public function actionRunReport()
	{
		if (!empty($_POST)) {
			if ($this->module) {
				$report_class = $this->module->id.'_Report'.$_POST['report-name'];
			} else {
				$report_class = 'Report'.$_POST['report-name'];
			}

			$report = new $report_class;
			$report->attributes = $_POST;

			if (!$report->validate()) {
				echo json_encode($report->errors);
				return;
			}

			$report->run();

			echo json_encode(array(
				'_report' => $this->renderPartial($report->getView(),array('report' => $report),true)
			));
		}
	}

	public function actionDownloadReport()
	{
		$this->sendCsvHeaders($_POST['report-name'].'.csv');

		if ($this->module) {
			$report_class = $this->module->id.'_Report'.$_POST['report-name'];
		} else {
			$report_class = 'Report'.$_POST['report-name'];
		}

		$report = new $report_class;
		$report->attributes = $_POST;

		if (@$_POST['validate_only']) {
			if (!$report->validate()) {
				echo json_encode($report->errors);
			} else {
				echo json_encode(array());
			}
			return;
		}

		if (!$report->validate()) {
			throw new Exception("Report errors: ".print_r($report->errors,true));
		}

		$report->run();

		echo $report->toCSV();
	}
}
