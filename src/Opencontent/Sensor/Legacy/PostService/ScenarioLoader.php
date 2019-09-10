<?php
namespace Opencontent\Sensor\Legacy\PostService;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Legacy\PostService\Scenarios\FallbackScenario;
use Opencontent\Sensor\Legacy\Repository;

class ScenarioLoader
{
    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var Post
     */
    private $post;

    /**
     * @var User
     */
    private $user;

    public function __construct($repository, Post $post, User $user)
    {
        $this->repository = $repository;
        $this->post = $post;
        $this->user = $user;
    }

    /**
     * @return ScenarioInterface
     */
    public function getScenario()
    {
        $scenarios = $this->repository->getScenarios();
        krsort($scenarios);

        foreach ($scenarios as $scenario){
            $this->repository->getLogger()->info("Parse scenario " . get_class($scenario));
            if ($scenario->match($this->post, $this->user) && $this->isValid($scenario)){
                $this->repository->getLogger()->info("Found valid scenario in " . get_class($scenario));
                return $scenario;
            }
        }

        $this->repository->getLogger()->info("Return fallback scenario");
        return new FallbackScenario();
    }

    /**
     * @param ScenarioInterface $scenario
     * @return bool
     */
    private function isValid(ScenarioInterface $scenario)
    {
        return count($scenario->getApprovers()) > 0;
    }
}