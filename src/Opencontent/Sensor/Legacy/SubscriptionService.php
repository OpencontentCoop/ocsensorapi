<?php

namespace Opencontent\Sensor\Legacy;

use Opencontent\Sensor\Api\Values\Post;
use Opencontent\Sensor\Api\Values\Subscription;
use Opencontent\Sensor\Api\Values\User;
use Opencontent\Sensor\Core\SubscriptionService as BaseSubscriptionService;
use SensorSubscriptionPersistentObject;

class SubscriptionService extends BaseSubscriptionService
{
    public function getUserSubscription(User $user, Post $post)
    {
        $subscriptionStorage = SensorSubscriptionPersistentObject::fetchByUserAndPost($user->id, $post->id);
        if ($subscriptionStorage instanceof SensorSubscriptionPersistentObject){
            $subscription = new Subscription();
            $subscription->id = (int)$subscriptionStorage->attribute('id');
            $subscription->postId = (int)$subscriptionStorage->attribute('post_id');
            $subscription->userId = (int)$subscriptionStorage->attribute('user_id');
            $subscription->createdAt = Utils::getDateTimeFromTimestamp($subscriptionStorage->attribute('created_at'));

            return $subscription;
        }

        return false;
    }

    public function getSubscriptionsByPost(Post $post)
    {
        return [];
    }

    public function getSubscriptionsByUser(User $user)
    {
        return [];
    }

    /**
     * @param User $user
     * @param Post $post
     * @return Subscription
     */
    public function createAndStoreSubscription(User $user, Post $post)
    {
        $now = time();
        $subscription = new Subscription();
        $subscription->postId = (int)$post->id;
        $subscription->userId = (int)$user->id;
        $subscription->createdAt = Utils::getDateTimeFromTimestamp($now);

        $subscriptionStorage = new SensorSubscriptionPersistentObject([
            'created_at' => $now,
            'post_id'=> $subscription->postId,
            'user_id'=> $subscription->userId,
        ]);
        $subscriptionStorage->store();

        return $subscription;
    }

    public function removeSubscription(Subscription $subscription)
    {
        $subscriptionStorage = SensorSubscriptionPersistentObject::fetch($subscription->id);
        if ($subscriptionStorage instanceof SensorSubscriptionPersistentObject){
            $subscriptionStorage->remove();
        }
    }
}