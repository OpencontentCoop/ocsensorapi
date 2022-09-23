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
            $subscription->createdTimestamp = (int)$subscriptionStorage->attribute('created_at');
            return $subscription;
        }

        return false;
    }

    public function getSubscriptionsByPost(Post $post)
    {
        return [];
    }

    public function countSubscriptionsByPost(Post $post)
    {
        return SensorSubscriptionPersistentObject::countByPost($post->id);
    }

    public function getSubscriptionsByUser(User $user)
    {
        $data = [];
        $subscriptions = SensorSubscriptionPersistentObject::fetchByUser($user->id);
        foreach ($subscriptions as $subscriptionStorage){
            $subscription = new Subscription();
            $subscription->id = (int)$subscriptionStorage->attribute('id');
            $subscription->postId = (int)$subscriptionStorage->attribute('post_id');
            $subscription->userId = (int)$subscriptionStorage->attribute('user_id');
            $subscription->createdAt = Utils::getDateTimeFromTimestamp($subscriptionStorage->attribute('created_at'));
            $subscription->createdTimestamp = (int)$subscriptionStorage->attribute('created_at');
            $data[] = $subscription;
        }

        return $data;
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
        $subscription->createdTimestamp = $now;

        $subscriptionStorage = new SensorSubscriptionPersistentObject([
            'created_at' => $subscription->createdTimestamp,
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