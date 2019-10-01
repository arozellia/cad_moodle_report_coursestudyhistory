<?php
declare(strict_types=1);

namespace report_coursestudyhistory;

use stdClass;

/**
 * Class courseuser
 * @package report_coursestudyhistory
 */
class courseuser
{
    /**
     * @var int
     */
    private  $userId;

    /**
     * @var int
     */
    private $userPictureSize = 100;

    /**
     * courseuser constructor.
     * @param int $userId
     */
    public function __construct(string $userId)
    {
        $this->setUserId($userId);
    }

    /**
     * @return int
     */
    public function getUserId() : string
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId(string $userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return stdClass
     * @throws \dml_exception
     */
    public function getUserDetails(): stdClass
    {
        global $OUTPUT, $DB;

        $user = $DB->get_record('user', array('id'=> $this->getUserId()));

        $userData = $user;
        $userData->userpicture = $OUTPUT->user_picture($user, array('size' => $this->userPictureSize));

        return $userData;
    }
}
