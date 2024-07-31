<?php

namespace Leantime\Plugins\ProjectOverview\Repositories;

use Carbon\CarbonImmutable;
use Leantime\Core\Db as DbCore;
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
     * @return array<string, mixed>
     */
    public function getTasks(?string $userId, ?string $searchTerm, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        $userIdQuery = isset($userId) ? ' AND editorId = :userId ' : '';
        $searchTermQuery = isset($searchTerm)
            ? " AND
        ticket.id LIKE CONCAT( '%', :searchTerm, '%') OR
        ticket.tags LIKE CONCAT( '%', :searchTerm, '%') OR
        ticket.headline LIKE CONCAT( '%', :searchTerm, '%') "
            : '';

        $sql =
            "SELECT
        ticket.id,
        ticket.headline,
        ticket.type,
        ticket.description,
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
        WHERE ticket.type <> 'milestone' AND ticket.status <> '0' AND (ticket.dateToFinish BETWEEN :dateFrom AND :dateTo) " .
            $userIdQuery .
            $searchTermQuery .
            'ORDER BY ticket.priority ASC';
        $stmn = $this->db->database->prepare($sql);

        if (isset($userId) && $userId !== '') {
            $stmn->bindValue(':userId', $userId, PDO::PARAM_INT);
        }

        if (isset($searchTerm) && $searchTerm !== '') {
            $stmn->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
        }

        $stmn->bindValue(':dateFrom', $dateFrom, PDO::PARAM_STR);
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
     * @return array<array<string, string>>
     */
    public function getSelectedMilestoneColor(string $milestoneId)
    {
        $sql = 'SELECT
        -- I dont know if this is considered bad practice, but renaming tags
        -- makes the code more understandable in the rest of the module
        ticket.tags AS color
        FROM
        zp_tickets AS ticket
        WHERE ticket.id = :milestoneId';

        $stmn = $this->db->database->prepare($sql);

        if ($milestoneId != '') {
            $stmn->bindValue(':milestoneId', $milestoneId, PDO::PARAM_INT);
        }

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }
}
