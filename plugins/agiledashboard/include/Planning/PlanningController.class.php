<?php
/**
 * Copyright (c) Enalean, 2012 - 2014. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */
 
require_once 'common/mvc2/PluginController.class.php';

/**
 * Handles the HTTP actions related to a planning.
 * 
 * TODO: Rename this file to PlanningController.class.php, to be consistent with
 * other classes. 
 */
class Planning_Controller extends MVC2_PluginController {

    const AGILE_DASHBOARD_TEMPLATE_NAME = 'agile_dashboard_template.xml';
    const PAST_PERIOD   = 'past';
    const FUTURE_PERIOD = 'future';
    const NUMBER_PAST_MILESTONES_SHOWN = 10;

    /** @var PlanningFactory */
    private $planning_factory;

    /** @var Planning_ShortAccessFactory */
    private $planning_shortaccess_factory;

    /** @var Planning_MilestoneFactory */
    private $milestone_factory;

    /** @var String */
    private $plugin_theme_path;

    /** @var ProjectManager */
    private $project_manager;

    /** @var AgileDashboard_XMLFullStructureExporter */
    private $xml_exporter;

    /** @var string */
    private $plugin_path;

    /** @var AgileDashboard_KanbanManager */
    private $kanban_manager;

    /** @var AgileDashboard_ConfigurationManager */
    private $config_manager;

    /** @var AgileDashboard_KanbanFactory */
    private $kanban_factory;

    /** @var PlanningPermissionsManager */
    private $planning_permissions_manager;

    public function __construct(
        Codendi_Request $request,
        PlanningFactory $planning_factory,
        Planning_ShortAccessFactory $planning_shortaccess_factory,
        Planning_MilestoneFactory $milestone_factory,
        ProjectManager $project_manager,
        AgileDashboard_XMLFullStructureExporter $xml_exporter,
        $plugin_theme_path,
        $plugin_path,
        AgileDashboard_KanbanManager $kanban_manager,
        AgileDashboard_ConfigurationManager $config_manager,
        AgileDashboard_KanbanFactory $kanban_factory,
        PlanningPermissionsManager $planning_permissions_manager
    ) {
        parent::__construct('agiledashboard', $request);
        
        $this->group_id                     = (int)$request->get('group_id');
        $this->planning_factory             = $planning_factory;
        $this->planning_shortaccess_factory = $planning_shortaccess_factory;
        $this->milestone_factory            = $milestone_factory;
        $this->project_manager              = $project_manager;
        $this->xml_exporter                 = $xml_exporter;
        $this->plugin_theme_path            = $plugin_theme_path;
        $this->plugin_path                  = $plugin_path;
        $this->kanban_manager               = $kanban_manager;
        $this->config_manager               = $config_manager;
        $this->kanban_factory               = $kanban_factory;
        $this->planning_permissions_manager = $planning_permissions_manager;
    }

    public function index() {
        if (! server_is_php_version_equal_or_greater_than_53()) {
            return $this->showPHP51Home();
        } else {
            return $this->showPHP53Home();
        }
    }

    private function showPHP51Home() {
        try {
            $plannings = $this->getPlanningsShortAccess($this->group_id);
        } catch (Planning_InvalidConfigurationException $exception) {
            $GLOBALS['Response']->addFeedback(Feedback::ERROR, $exception->getMessage());
            $plannings = array();
        }

        if (empty($plannings)) {
            return $this->showEmptyHome();
        }

        $presenter = new Planning_Presenter_PHP51HomePresenter(
            $plannings,
            $this->plugin_theme_path,
            $this->group_id
        );
        return $this->renderToString('home_php51', $presenter);
    }

    private function showPHP53Home() {
        $user = $this->request->getCurrentUser();

        $plannings = $this->planning_factory->getNonLastLevelPlannings(
            $user,
            $this->group_id
        );
        $last_plannings = $this->planning_factory->getLastLevelPlannings($user, $this->group_id);

        $kanban_is_activated = $this->config_manager->kanbanIsActivatedForProject($this->group_id);
        $scrum_is_configured = ! empty($plannings) || ! empty($last_plannings);

        if (! $scrum_is_configured && ! $kanban_is_activated) {
            return $this->showEmptyHome();
        }

        $presenter = new Planning_Presenter_HomePresenter(
            $this->getMilestoneAccessPresenters($plannings),
            $this->group_id,
            $this->getLastLevelMilestonesPresenters($last_plannings, $user),
            $this->request->get('period'),
            $this->getProjectFromRequest()->getPublicName(),
            $kanban_is_activated,
            $user,
            $this->kanban_manager->getTrackersWithKanbanUsageAndHierarchy($this->group_id),
            $this->getKanbanSummaryPresenters(),
            $this->config_manager->scrumIsActivatedForProject($this->group_id),
            $scrum_is_configured,
            $this->config_manager->getScrumTitle($this->group_id),
            $this->config_manager->getKanbanTitle($this->group_id)
        );
        return $this->renderToString('home', $presenter);
    }

    private function getKanbanSummaryPresenters() {
        $kanban_presenters = array();

        $user = $this->request->getCurrentUser();

        $list_of_kanban = $this->kanban_factory->getListOfKanbansForProject(
            $user,
            $this->group_id
        );

        foreach ($list_of_kanban as $kanban_for_project) {
            $kanban_presenters[] = new AgileDashboard_Presenter_KanbanSummaryPresenter(
                $kanban_for_project,
                new AgileDashboard_KanbanItemDao()
            );
        }

        return $kanban_presenters;
    }

    /**
     * Home page for when there is nothing set-up.
     */
    private function showEmptyHome() {
        $presenter = new Planning_Presenter_EmptyHomePresenter(
            $this->group_id,
            $this->isUserAdmin()
        );
        return $this->renderToString('empty-home', $presenter);
    }

    /**
     * @return Planning_Presenter_MilestoneAccessPresenter
     */
    private function getMilestoneAccessPresenters($plannings) {
        $milestone_access_presenters = array();
        foreach ($plannings as $planning) {
            $milestone_type      = $planning->getPlanningTracker();
            $milestone_presenter = new Planning_Presenter_MilestoneAccessPresenter(
                $this->getPlanningMilestonesDependingOnTimePeriodOrStatus($planning),
                $milestone_type->getName()
            );

            $milestone_access_presenters[] = $milestone_presenter;
        }

        return $milestone_access_presenters;
    }

    private function getPlanningMilestonesDependingOnTimePeriodOrStatus(Planning $planning) {
        $set_in_time = $this->planning_factory->canPlanningBeSetInTime($planning->getPlanningTracker());

        if ($set_in_time) {
            $milestones = $this->getPlanningMilestonesForTimePeriod($planning);
        } else {
            $milestones = $this->getPlanningMilestonesByStatus($planning);
        }

        return $milestones;
    }

    /**
     * @param Planning[] $last_plannings
     * @param PFUser $user
     * @return Planning_Presenter_LastLevelMilestone[]
     */
    private function getLastLevelMilestonesPresenters($last_plannings, PFUser $user) {
        $presenters = array();

        foreach ($last_plannings as $last_planning) {
            $presenters[] = new Planning_Presenter_LastLevelMilestone(
                $this->getMilestoneSummaryPresenters($last_planning, $user),
                $last_planning->getPlanningTracker()->getName()
            );
        }

        return $presenters;
    }

    /**
     * @return Planning_Presenter_MilestoneSummaryPresenter[]
     */
    private function getMilestoneSummaryPresenters(Planning $last_planning, PFUser $user) {
        $presenters   = array();
        $has_cardwall = $this->hasCardwall($last_planning);

        $last_planning_current_milestones = $this->getPlanningMilestonesDependingOnTimePeriodOrStatus($last_planning);

        if (empty($last_planning_current_milestones)) {
            return $presenters;
        }

        foreach ($last_planning_current_milestones as $milestone) {
            $this->milestone_factory->addMilestoneAncestors($user, $milestone);
            $milestone = $this->milestone_factory->updateMilestoneContextualInfo($user, $milestone);

            if ($milestone->hasUsableBurndownField()) {
                $burndown_data = $milestone->getBurndownData($user);

                $presenters[] = new Planning_Presenter_MilestoneBurndownSummaryPresenter(
                    $milestone,
                    $this->plugin_path,
                    $has_cardwall,
                    $burndown_data
                );
            } else {
                $presenters[] = new Planning_Presenter_MilestoneSummaryPresenter(
                    $milestone,
                    $this->plugin_path,
                    $has_cardwall,
                    $this->milestone_factory->getMilestoneStatusCount($user, $milestone)
                );
            }
        }

        return $presenters;
    }

    /**
     * @return Planning_Milestone[]
     */
    private function getPlanningMilestonesForTimePeriod(Planning $planning) {
        $user = $this->request->getCurrentUser();

        switch ($this->request->get('period')) {
            case self::PAST_PERIOD:
                return $this->milestone_factory->getPastMilestones(
                    $user,
                    $planning,
                    self::NUMBER_PAST_MILESTONES_SHOWN
                );
            case self::FUTURE_PERIOD:
                return $this->milestone_factory->getAllFutureMilestones(
                    $user,
                    $planning
                );
            default:
                return $this->milestone_factory->getAllCurrentMilestones(
                    $user,
                    $planning
                );
        }
    }

    private function getPlanningMilestonesByStatus(Planning $planning) {
        $user = $this->request->getCurrentUser();

        switch ($this->request->get('period')) {
            case self::PAST_PERIOD:
                return $this->milestone_factory->getAllClosedMilestones(
                    $user,
                    $planning
                );

            case self::FUTURE_PERIOD:
                return $this->milestone_factory->getAllOpenMilestones(
                    $user,
                    $planning
                );
            default:
                return $this->milestone_factory->getAllOpenMilestones(
                    $user,
                    $planning
                );
        }
    }

    /**
     * @return bool
     */
    private function isUserAdmin() {
        return $this->request->getProject()->userIsAdmin($this->request->getCurrentUser());
    }

    /**
     * Redirects a non-admin user to the agile dashboard home page
     */
    private function redirectNonAdmin() {
        if (! $this->isUserAdmin()) {
            $this->redirect(array('group_id'=>$this->group_id));
        }
    }
    
    public function new_() {
        $planning  = $this->planning_factory->buildNewPlanning($this->group_id);
        $presenter = $this->getFormPresenter($this->request->getCurrentUser(), $planning);

        return $this->renderToString('new', $presenter);
    }

    public function importForm() {
        $this->redirectNonAdmin();

        $template_file = new Valid_File('template_file');
        $template_file->required();

        if ($this->request->validFile($template_file)) {
            $this->importConfiguration();
        }

        $presenter = new Planning_ImportTemplateFormPresenter($this->group_id);
        return $this->renderToString('import', $presenter);
    }

    private function importConfiguration() {
        $xml_importer = new ProjectXMLImporter(
            EventManager::instance(),
            ProjectManager::instance(),
            new XML_RNGValidator(),
            new UGroupManager(),
            UserManager::instance(),
            new XMLImportHelper(),
            new ProjectXMLImporterLogger()
        );

        try {
            $xml_importer->import($this->group_id, $_FILES["template_file"]["tmp_name"]);
            $GLOBALS['Response']->addFeedback(Feedback::INFO, $GLOBALS['Language']->getText('plugin_agiledashboard', 'import_template_success') );
        } catch (Exception $e) {
            $GLOBALS['Response']->addFeedback(Feedback::ERROR, $GLOBALS['Language']->getText('plugin_agiledashboard', 'cannot_import') );
        }
    }

    /**
     * Exports the agile dashboard configuration as an XML file
     */
    public function exportToFile() {
        try {
            $project = $this->getProjectFromRequest();
            $xml = $this->getFullConfigurationAsXML($project);
        } catch (Exception $e) {
            $GLOBALS['Response']->addFeedback(Feedback::ERROR, $GLOBALS['Language']->getText('plugin_agiledashboard', 'export_failed'));
            $this->redirect(array('group_id'=>$this->group_id, 'action'=>'admin'));
        }

        $GLOBALS['Response']->sendXMLAttachementFile($xml, self::AGILE_DASHBOARD_TEMPLATE_NAME);
    }

    /**
     * @return Project
     * @throws Project_NotFoundException
     */
    private function getProjectFromRequest() {
        return $this->project_manager->getValidProject($this->group_id);
    }

    private function getFullConfigurationAsXML(Project $project) {
        return $this->xml_exporter->export($project);
    }

    public function create() {
        $this->checkUserIsAdmin();
        $validator = new Planning_RequestValidator($this->planning_factory, $this->kanban_factory);
        
        if ($validator->isValid($this->request)) {
            $this->planning_factory->createPlanning(
                $this->group_id,
                PlanningParameters::fromArray(
                    $this->request->get('planning')
                )
            );
            
            $this->redirect(array('group_id' => $this->group_id, 'action' => 'admin'));
        } else {
            // TODO: Error message should reflect validation detail
            $this->addFeedback('error', $GLOBALS['Language']->getText('plugin_agiledashboard', 'planning_all_fields_mandatory'));
            $this->redirect(array('group_id' => $this->group_id, 'action' => 'new'));
        }
    }
    
    public function edit() {
        $planning  = $this->planning_factory->getPlanning($this->request->get('planning_id'));
        $presenter = $this->getFormPresenter($this->request->getCurrentUser(), $planning);
        
        return $this->renderToString('edit', $presenter);
    }
    
    private function getFormPresenter(PFUser $user, Planning $planning) {
        $group_id = $planning->getGroupId();

        $available_trackers            = $this->planning_factory->getAvailableBacklogTrackers($user, $group_id);
        $available_planning_trackers   = $this->planning_factory->getAvailablePlanningTrackers($user, $group_id);
        $cardwall_admin                = $this->getCardwallConfiguration($planning);
        $available_planning_trackers[] = $planning->getPlanningTracker();
        $kanban_tracker_ids            = $this->kanban_factory->getKanbanTrackerIds($group_id);

        $planning_trackers_filtered = $this->getPlanningTrackersFiltered(
            $available_planning_trackers,
            $kanban_tracker_ids,
            $planning
        );

        $backlog_trackers_filtered = $this->getBacklogTrackersFiltered(
            $available_trackers,
            $kanban_tracker_ids,
            $planning
        );

        return new Planning_FormPresenter(
            $this->planning_permissions_manager,
            $planning,
            $backlog_trackers_filtered,
            $planning_trackers_filtered,
            $cardwall_admin
        );
    }

    private function getPlanningTrackersFiltered(array $trackers, array $kanban_tracker_ids, Planning $planning) {
        $trackers_filtered = array();

        foreach ($this->getPlanningTrackerPresenters($trackers, $planning) as $tracker) {
            $trackers_filtered[] = array(
                'name'     => $tracker->getName(),
                'id'       => $tracker->getId(),
                'selected' => $tracker->selectedIfPlanningTracker(),
                'disabled' => in_array($tracker->getId(), $kanban_tracker_ids)
            );
        }

        return $trackers_filtered;
    }

    private function getBacklogTrackersFiltered(array $trackers, array $kanban_tracker_ids, Planning $planning) {
        $trackers_filtered = array();

        foreach ($this->getPlanningTrackerPresenters($trackers, $planning) as $tracker) {
            $trackers_filtered[] = array(
                'name'     => $tracker->getName(),
                'id'       => $tracker->getId(),
                'selected' => $tracker->selectedIfBacklogTracker(),
                'disabled' => in_array($tracker->getId(), $kanban_tracker_ids)
            );
        }

        return $trackers_filtered;
    }

    private function getPlanningTrackerPresenters(array $trackers, Planning $planning) {
        $tracker_presenters = array();

        foreach ($trackers as $tracker) {
            $tracker_presenters[] = new Planning_TrackerPresenter($planning, $tracker);
        }

        return $tracker_presenters;
    }

    private function hasCardwall(Planning $planning) {
        $tracker = $planning->getPlanningTracker();
        $enabled = false;

        EventManager::instance()->processEvent(
            AGILEDASHBOARD_EVENT_IS_CARDWALL_ENABLED,
            array(
                'tracker' => $tracker,
                'enabled'    => &$enabled,
            )
        );

        return $enabled;
    }

    private function getCardwallConfiguration(Planning $planning) {
        $tracker  = $planning->getPlanningTracker();
        $view     = null;

        EventManager::instance()->processEvent(
            AGILEDASHBOARD_EVENT_PLANNING_CONFIG,
            array(
                'tracker' => $tracker,
                'view'    => &$view,
            )
        );

        return $view;
    }

    public function update() {
        $this->checkUserIsAdmin();
        $validator = new Planning_RequestValidator($this->planning_factory, $this->kanban_factory);
        
        if ($validator->isValid($this->request)) {
            $this->planning_factory->updatePlanning(
                $this->request->get('planning_id'),
                $this->group_id,
                PlanningParameters::fromArray(
                    $this->request->get('planning')
                )
            );
        } else {
            $this->addFeedback('error', $GLOBALS['Language']->getText('plugin_agiledashboard', 'planning_all_fields_mandatory'));
        }

        $this->updateCardwallConfig();

        $this->redirect(array('group_id'    => $this->group_id,
                              'planning_id' => $this->request->get('planning_id'),
                              'action'      => 'edit'));
    }

    private function updateCardwallConfig() {
        $tracker = $this->getPlanning()->getPlanningTracker();

        EventManager::instance()->processEvent(
            AGILEDASHBOARD_EVENT_PLANNING_CONFIG_UPDATE,
            array(
                'request' => $this->request,
                'tracker' => $tracker,
            )
        );
    }

    public function delete() {
        $this->checkUserIsAdmin();
        $this->planning_factory->deletePlanning($this->request->get('planning_id'));
        return $this->redirect(array('group_id' => $this->group_id, 'action' => 'admin'));
    }

    /**
     * @return BreadCrumb_BreadCrumbGenerator
     */
    public function getBreadcrumbs($plugin_path) {
        return new BreadCrumb_AgileDashboard();
    }

    public function getMoreMilestones() {
        $offset = $this->request->get('offset', 'uint', 0);
        $planning = $this->planning_factory->getPlanning($this->request->get('planning_id'));
        $short_access = $this->planning_shortaccess_factory->getShortAccessForPlanning(
            $planning,
            $this->getCurrentUser(),
            $this->milestone_factory,
            $this->plugin_theme_path,
            $offset
        );

        $this->render('shortaccess-milestones', $short_access);
    }

    /**
     *
     * @param int $projectId
     * @return Planning_ShortAccess[]
     */
    private function getPlanningsShortAccess($projectId) {
        return $this->planning_shortaccess_factory->getPlanningsShortAccess(
            $this->getCurrentUser(),
            $projectId,
            $this->milestone_factory,
            $this->plugin_theme_path
        );
    }
    
    private function getPlanning() {
        $planning_id = $this->request->get('planning_id');
        return $this->planning_factory->getPlanning($planning_id);
    }
}

?>
