<?php

final class SprintTransaction  {

  private $viewer;
  private $task_points = array();
  private $task_statuses = array();
  private $task_in_sprint = array();

  public function setViewer ($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function buildDailyData($events, $before, $start, $end, $dates, $xactions, $project) {

    $query = id(new SprintQuery());

    foreach ($events as $event) {
      $date = null;
      $xaction = $xactions[$event['transactionPHID']];
      $xaction_date = $xaction->getDateCreated();
      $task_phid = $xaction->getObjectPHID();
      $project_phid = $project->getPHID();
      $project_phids = $xaction->getObject()->getProjectPHIDs();

      if (in_array($project_phid, $project_phids)) {
        $points = $query->getStoryPoints($task_phid);
        $date = phabricator_format_local_time($xaction_date, $this->viewer, 'D M j');

        if ($xaction_date < $start) {

          switch ($event['type']) {
            case "task-add":
              $this->AddTasksBefore($before, $dates);
              break;
            case "close":
              // A task was closed, mark it as done
  //             $this->CloseTasksBefore($before, $dates);
             // $this->ClosePointsBefore($before, $points, $dates);
              break;
            case "reopen":
              // A task was reopened, subtract from done
    //          $this->ReopenedTasksBefore($before, $dates);
    //          $this->ReopenedPointsBefore($before, $points, $dates);
              break;
            case "points":
              // Points were changed
              $old_point_value = $xaction->getOldValue();
              $this->ChangePointsBefore($before, $points, $old_point_value, $dates);
              break;
          }
        }

        if ($xaction_date > $end) {
          continue;
        }

        // Determine which date to attach this data to

        if ($xaction_date > $start && $xaction_date < $end) {

          switch ($event['type']) {
            case "create":
 //            $this->AddTaskCreated($task_phid);
              // Will be accounted for by "task-add" when the project is added
              // But we still include it so it shows on the Events list
              break;
            case "task-add":
              // A task was added to the sprint
             $this->AddTasksToday($date, $dates);
              $this->AddTaskInSprint($task_phid);
              break;
            case "task-remove":
              break;
            case "close":
              // A task was closed, mark it as done
              $this->CloseTasksToday($date, $dates);
              $this->ClosePointsToday($date, $points, $dates);
              break;
            case "reopen":
              // A task was reopened, subtract from done
              $this->ReopenedTasksToday($date, $dates);
              $this->ReopenedPointsToday($date, $points, $dates);
              break;
            case "points":
              // Points were changed
              if ($this->task_in_sprint[$task_phid]) {
                $old_point_value = $xaction->getOldValue();
                $this->changePoints($date, $task_phid, $points, $old_point_value, $dates);
//                $this->closePoints($date, $task_phid, $points, $old_point_value, $dates);
              }
              break;
          }
        }
      }
    }
  return $dates;
}

  public function buildStatArrays($tasks) {
    foreach ($tasks as $task) {
      $this->task_points[$task->getPHID()] = 0;
      $this->task_statuses[$task->getPHID()] = null;
      $this->task_in_sprint[$task->getPHID()] = 0;
    }
    return;
  }

  private function AddTasksBefore($before, $dates) {
    $before->setTasksAddedBefore();
    return $dates;
  }

  private function CloseTasksBefore($before, $dates) {
    $before->setTasksClosedBefore();
    return $dates;
  }

  private function ReopenedTasksBefore($before, $dates) {
    $before->setTasksReopenedBefore();
    return $dates;
  }

  private function ReopenedPointsBefore($before, $points, $dates) {
    $before->setPointsReopenedBefore($points);
    return $dates;
  }

  private function AddTasksToday($date, $dates) {
    $dates[$date]->setTasksAddedToday();
    return $dates;
  }

  private function CloseTasksToday($date, $dates) {
    $dates[$date]->setTasksClosedToday();
    return $dates;
  }

  private function ReopenedTasksToday($date, $dates) {
   $dates[$date]->setTasksReopenedToday();
    return $dates;
  }

  private function AddPointsBefore($before, $points, $dates) {
    $before->setPointsAddedBefore($points);
    return $dates;
  }

  private function ClosePointsBefore($before, $points, $dates) {
    $before->setPointsClosedBefore($points);
    return $dates;
  }

  private function ChangePointsBefore($before, $points,  $old_point_value) {
    $points = $points - $old_point_value;
    $before->setPointsAddedBefore($points);
  }

  private function AddPointsToday($date, $points, $dates) {
    $dates[$date]->setPointsAddedToday($points);
    return $dates;
  }

  private function ClosePointsToday($date, $points, $dates) {
   $dates[$date]->setPointsClosedToday($points);
    return $dates;
  }

  private function ReopenedPointsToday($date, $points, $dates) {
    $dates[$date]->setPointsReopenedToday($points);
    return $dates;
  }

  private function AddTaskInSprint($task_phid) {
    $this->task_in_sprint[$task_phid] = 1;
    return $this->task_in_sprint[$task_phid];
  }

  private function changePoints($date, $task_phid, $points, $old_point_value, $dates) {

    // Adjust points for that day
    $this->task_points[$task_phid] = $points - $old_point_value;
    $dates[$date]->setPointsAddedToday($this->task_points[$task_phid]);
    return $dates;
  }

  private function closePoints($date, $task_phid, $points, $old_point_value, $dates)
  {
    // If the task is closed, adjust completed points as well
    if (isset($this->task_statuses[$task_phid]) && $this->task_statuses[$task_phid] == 'closed') {
      $this->task_points[$task_phid] = $points - $old_point_value;
      $dates[$date]->setPointsClosedToday($this->task_points[$task_phid]);
    }
  }

  private function AddTaskCreated($task_phid) {
    $this->task_created = $task_phid;
  }
}