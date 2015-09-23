<?php

final class DrydockLeaseViewController extends DrydockLeaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$lease) {
      return new Aphront404Response();
    }

    $lease_uri = $this->getApplicationURI('lease/'.$lease->getID().'/');

    $title = pht('Lease %d', $lease->getID());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($lease);
    $properties = $this->buildPropertyListView($lease, $actions);

    $pager = new PHUIPagerView();
    $pager->setURI(new PhutilURI($lease_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->setViewer($viewer)
      ->withLeaseIDs(array($lease->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = id(new DrydockLogListView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->render();
    $log_table->appendChild($pager);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $lease_uri);

    $locks = $this->buildLocksTab($lease->getPHID());
    $commands = $this->buildCommandsTab($lease->getPHID());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties, pht('Properties'))
      ->addPropertyList($locks, pht('Slot Locks'))
      ->addPropertyList($commands, pht('Commands'));

    $log_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Lease Logs'))
      ->setTable($log_table);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $log_box,
      ),
      array(
        'title' => $title,
      ));

  }

  private function buildActionListView(DrydockLease $lease) {
    $viewer = $this->getViewer();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($lease);

    $id = $lease->getID();

    $can_release = $lease->canRelease();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $lease,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Release Lease'))
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI("/lease/{$id}/release/"))
        ->setWorkflow(true)
        ->setDisabled(!$can_release || !$can_edit));

    return $view;
  }

  private function buildPropertyListView(
    DrydockLease $lease,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    switch ($lease->getStatus()) {
      case DrydockLeaseStatus::STATUS_ACTIVE:
        $status = pht('Active');
        break;
      case DrydockLeaseStatus::STATUS_RELEASED:
        $status = pht('Released');
        break;
      case DrydockLeaseStatus::STATUS_EXPIRED:
        $status = pht('Expired');
        break;
      case DrydockLeaseStatus::STATUS_PENDING:
        $status = pht('Pending');
        break;
      case DrydockLeaseStatus::STATUS_BROKEN:
        $status = pht('Broken');
        break;
      default:
        $status = pht('Unknown');
        break;
    }

    $view->addProperty(
      pht('Status'),
      $status);

    $view->addProperty(
      pht('Resource Type'),
      $lease->getResourceType());

    $resource = id(new DrydockResourceQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($lease->getResourceID()))
      ->executeOne();

    if ($resource !== null) {
      $view->addProperty(
        pht('Resource'),
        $this->getViewer()->renderHandle($resource->getPHID()));
    } else {
      $view->addProperty(
        pht('Resource'),
        pht('No Resource'));
    }

    $until = $lease->getUntil();
    if ($until) {
      $until_display = phabricator_datetime($until, $viewer);
    } else {
      $until_display = phutil_tag('em', array(), pht('Never'));
    }
    $view->addProperty(pht('Expires'), $until_display);

    $attributes = $lease->getAttributes();
    if ($attributes) {
      $view->addSectionHeader(
        pht('Attributes'), 'fa-list-ul');
      foreach ($attributes as $key => $value) {
        $view->addProperty($key, $value);
      }
    }

    return $view;
  }

}
