<?php

namespace Leantime\Plugins\ProjectOverview\Repositories;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Leantime\Core\Db\Db as DbCore;
use PDO;

/**
 * This is the project overview repository, that makes (hopefully) the relevant sql queries.
 */
class ProjectOverview
{
    /**
     * @var DbCore|null - db connection
     */
    private null|DbCore $db = null;

    /**
     * __construct - get db connection
     *
     * @access public
     * @return void
     */
    public function __construct(DbCore $db)
    {
        $this->db = $db;
    }

    /**
     * getTasks - retrieve tasks based on given parameters
     *
     * @param array<int, string>|null $userIdArray    - array of user IDs to filter tasks by, defaults to null
     * @param string|null             $searchTerm     - search term to filter tasks by, defaults to null
     * @param CarbonInterface         $dateFrom       - start date to filter tasks by
     * @param CarbonInterface         $dateTo         - end date to filter tasks by
     * @param int                     $noDueDate      include tasks without due date set
     * @param int                     $overdueTickets include overdue tasks
     * @param string                  $sortBy         sort tickets by.
     * @param string                  $sortOrder      sort order.
     * @return array<int, string> - array containing the retrieved tasks
     */
    public function getTasks(?array $userIdArray, ?string $searchTerm, CarbonInterface $dateFrom, CarbonInterface $dateTo, int $noDueDate, int $overdueTickets, ?string $sortBy, ?string $sortOrder): array
    {
        $userIdQuery = '';
        if (!empty($userIdArray)) {
            $placeholders = ':' . implode(', :', array_values($userIdArray));
            $userIdQuery = ' AND editorId IN (' . $placeholders . ') ';
        }
        $searchTermQuery = isset($searchTerm)
            ? " AND
        (ticket.id LIKE CONCAT( '%', :searchTerm, '%') OR
        ticket.tags LIKE CONCAT( '%', :searchTerm, '%') OR
        ticket.headline LIKE CONCAT( '%', :searchTerm, '%')) "
            : '';

        $orderBy = 'ORDER BY ticket.priority ASC';
        if ($sortBy && $sortOrder) {
            // We treat editorLastname different than the other sorts, as this is a property on user.
            // Furthermore, if we want to sort projects by title and not id, we should do something similar.
            // But for now we are sorting projects by id, let's see if that works.
            if ($sortBy === 'editorLastname') {
                $orderBy = 'ORDER BY t2.lastname ' . $sortOrder;
            } else {
                $orderBy = 'ORDER BY ticket.' . $sortBy .  ' ' . $sortOrder;
            }
        }
        // In the database, if a task does not have a due date, it has "0000-00-00 00:00:00" (as opposed to the more logical NULL)
        $dateQuery = $noDueDate === 1 ? "OR ticket.dateToFinish = '0000-00-00 00:00:00'" : '';
        // Todo: Hardcoded date to a time before we used Leantime.
        $fromDateForQuery = $overdueTickets === 1 ? CarbonImmutable::createFromFormat('Y-m-d', '2023-03-14')->endOfDay() : $dateFrom;
        $sql =
            "SELECT
        ticket.id,
        ticket.headline,
        ticket.type,
        ticket.description,
        ticket.planHours,
        ticket.hourRemaining,
        ticket.date,
        ticket.milestoneid,
        CAST(ticket.dateToFinish AS DATE) as dueDate,
        ticket.projectId,
        ticket.tags,
        ticket.priority,
        ticket.status,
        t1.id AS authorId,
        t1.firstname AS authorFirstname,
        t1.lastname AS authorLastname,
        t2.id AS editorId,
        t2.firstname AS editorFirstname,
        t2.lastname AS editorLastname
        FROM
        zp_tickets AS ticket
        LEFT JOIN zp_user AS t1 ON ticket.userId = t1.id
        LEFT JOIN zp_user AS t2 ON ticket.editorId = t2.id
        WHERE ticket.type <> 'milestone' AND ticket.status <> '0' AND (ticket.dateToFinish BETWEEN :dateFrom AND :dateTo " . $dateQuery . ') ' .
            $userIdQuery .
            $searchTermQuery .
            $orderBy;
        $stmn = $this->db->database->prepare($sql);

        if (!empty($userIdArray)) {
            foreach ($userIdArray as $id) {
                $stmn->bindValue(':' . $id, $id, PDO::PARAM_INT);
            }
        }

        if (isset($searchTerm) && $searchTerm !== '') {
            $stmn->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
        }

        $stmn->bindValue(':dateFrom', $fromDateForQuery, PDO::PARAM_STR);
        $stmn->bindValue(':dateTo', $dateTo, PDO::PARAM_STR);

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMilestonesByProjectId(string $projectId): array
    {
        $sql = "SELECT
        ticket.id,
        ticket.headline,
        ticket.projectId,
        -- I dont know if this is considered bad practice, but renaming tags
        -- makes the code more understandable in the rest of the module
        ticket.tags AS color
        FROM
        zp_tickets AS ticket
        WHERE ticket.type = 'milestone' AND projectId = :projectId";

        $stmn = $this->db->database->prepare($sql);

        if ($projectId != '') {
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        }

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }

    /**
     * Get all projects from the database
     *
     * @access public
     * @return array<string, mixed> Returns an array of all projects
     */
    public function getAllProjects(): array
    {
        $sql = 'SELECT * FROM zp_projects WHERE state != "1" OR state IS NULL LIMIT 200';

        $stmn = $this->db->database->prepare($sql);

        $stmn->execute();
        $values = $stmn->fetchAll(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        $projects = [];

        foreach ($values as $value) {
            $id = $value['id'];
            unset($value[0]); // Remove numeric index

            // Remove all numeric indices
            foreach ($value as $key => $item) {
                if (is_numeric($key)) {
                    unset($value[$key]);
                }
            }

            $projects[$id] = $value;
        }

        return $projects;
    }
}
