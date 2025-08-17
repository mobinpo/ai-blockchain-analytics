<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use Laravel\Cashier\Subscription;

// Set Laravel environment
putenv('APP_ENV=local');

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔄 Syncing Stripe subscription to local database...\n\n";
    
    // Get or create the user based on Stripe customer info
    $stripeCustomerId = 'cus_SsTY5BWxAoUTe7'; // From the subscription response
    
    // First try to find user by Stripe ID
    $user = User::where('stripe_id', $stripeCustomerId)->first();
    
    if (!$user) {
        // Create the user if they don't exist
        echo "👤 Creating user for Stripe customer: {$stripeCustomerId}\n";
        $user = User::create([
            'name' => 'Mobin Poursalami',
            'email' => 'mobinpou2@gmail.com',
            'password' => bcrypt('password'),
            'stripe_id' => $stripeCustomerId,
            'email_verified_at' => now(),
        ]);
        echo "✅ User created with ID: {$user->id}\n";
    }
    
    echo "👤 User: {$user->email} (Stripe ID: {$user->stripe_id})\n";
    
    // Check if user has Stripe customer ID
    if (!$user->stripe_id) {
        throw new Exception('User does not have a Stripe customer ID');
    }
    
    // Get Stripe subscriptions for this customer
    \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
    $stripeSubscriptions = \Stripe\Subscription::all([
        'customer' => $user->stripe_id,
        'limit' => 10
    ]);
    
    echo "📋 Found " . count($stripeSubscriptions->data) . " Stripe subscriptions\n\n";
    
    foreach ($stripeSubscriptions->data as $stripeSubscription) {
        echo "🔍 Processing Stripe subscription: {$stripeSubscription->id}\n";
        echo "   Status: {$stripeSubscription->status}\n";
        echo "   Plan: {$stripeSubscription->items->data[0]->price->id}\n";
        
        // Check if this subscription already exists in our database
        $existingSubscription = Subscription::where('stripe_id', $stripeSubscription->id)->first();
        
        if ($existingSubscription) {
            echo "   ✅ Already exists in database (ID: {$existingSubscription->id})\n";
        } else {
            // Create the subscription in our database
            echo "   🆕 Creating new database record...\n";
            
            $subscription = new Subscription();
            $subscription->user_id = $user->id;
            $subscription->type = 'starter'; // Default type, could be derived from price ID
            $subscription->stripe_id = $stripeSubscription->id;
            $subscription->stripe_status = $stripeSubscription->status;
            $subscription->stripe_price = $stripeSubscription->items->data[0]->price->id;
            $subscription->quantity = $stripeSubscription->items->data[0]->quantity;
            $subscription->trial_ends_at = $stripeSubscription->trial_end ? 
                \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null;
            $subscription->ends_at = null; // Active subscription
            $subscription->created_at = \Carbon\Carbon::createFromTimestamp($stripeSubscription->created);
            $subscription->updated_at = now();
            
            $subscription->save();
            
            echo "   ✅ Subscription saved to database (ID: {$subscription->id})\n";
            
            // Create subscription items
            foreach ($stripeSubscription->items->data as $item) {
                $subscriptionItem = new \Laravel\Cashier\SubscriptionItem();
                $subscriptionItem->subscription_id = $subscription->id;
                $subscriptionItem->stripe_id = $item->id;
                $subscriptionItem->stripe_product = $item->price->product;
                $subscriptionItem->stripe_price = $item->price->id;
                $subscriptionItem->quantity = $item->quantity;
                $subscriptionItem->created_at = \Carbon\Carbon::createFromTimestamp($item->created);
                $subscriptionItem->updated_at = now();
                
                $subscriptionItem->save();
                
                echo "   📦 Subscription item saved (ID: {$subscriptionItem->id})\n";
            }
        }
        echo "\n";
    }
    
    // Verify user is now subscribed
    $user = $user->fresh(); // Reload user
    echo "🎉 Final verification:\n";
    echo "   User subscribed: " . ($user->subscribed() ? 'YES' : 'NO') . "\n";
    echo "   Subscription name: " . ($user->subscription()->name ?? 'NONE') . "\n";
    echo "   Subscription status: " . ($user->subscription()->stripe_status ?? 'NONE') . "\n";
    
    echo "\n✅ Sync completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}