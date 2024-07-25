<?php

namespace Leantime\Plugins\ProjectOverview\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Eventhelpers as EventhelperCore;
use Leantime\Core\Db as DbCore;
use Leantime\Core\Language as LanguageCore;
use Leantime\Domain\Users\Services\Users;
use PDO;

/**
 *
 */
class ProjectOverview
{
    /**
     * __construct - get db connection
     *
     * @access public
     * @return void
     */
    public function __construct(DbCore $db, LanguageCore $language)
    {
        $this->db = $db;
        $this->language = $language;
    }

    /**
     * @return array
     */
    public function getTasks(): array
    {
        $sql = "SELECT
        ticket.id,
        ticket.headline,
        ticket.type,
        ticket.description,
        ticket.date,
        ticket.milestoneid,
        ticket.dateToFinish,
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
    WHERE ticket.type <> 'milestone' AND ticket.status <> '0'
    ORDER BY ticket.priority ASC";

        $stmn = $this->db->database->prepare($sql);

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }

    /**
     * @return array
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

        if (isset($projectId) && $projectId != '') {
            $stmn->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        }

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }

    public function getSelectedMilestoneColor(string $milestoneId) {
        $sql = "SELECT
        -- I dont know if this is considered bad practice, but renaming tags
        -- makes the code more understandable in the rest of the module
        ticket.tags AS color
        FROM
        zp_tickets AS ticket
        WHERE ticket.id = :milestoneId";

        $stmn = $this->db->database->prepare($sql);

        if (isset($milestoneId) && $milestoneId != '') {
            $stmn->bindValue(':milestoneId', $milestoneId, PDO::PARAM_INT);
        }

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }
}
