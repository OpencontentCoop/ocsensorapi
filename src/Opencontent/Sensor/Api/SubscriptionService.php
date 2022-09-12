<?php

namespace Opencontent\Sensor\Api;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Subscription;
use Opencontent\Sensor\Api\Values\User;

interface SubscriptionService
{
    /**
     * @param User $user
     * @param Post $post
     * @return Subscription|false
     */
    public function getUserSubscription(User $user, Post $post);

    /**
     * @param User $user
     * @param Post $post
     * @return Subscription
     */
    public function createAndStoreSubscription(User $user, Post $post);

    public function removeSubscription(Subscription $subscription);

    /**
     * @param Post $post
     * @return User[]
     */
    public function getSubscriptionsByPost(Post $post);

    public function getSubscriptionsByUser(User $user);
}