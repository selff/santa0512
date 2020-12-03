# Santa0512

## Is telegram bot with "Secret Santa" for a group of friends.

This bot will help you to distribute gift giving among all registered friends or colleagues so that this distribution is secret. The bot also supports the ability to specify the wishlist of the participants.

1. Create a new telegram bot using https://t.me/botfather
2. For a new bot to work using a webhook, you need a domain with a certificate for a secure https connection.
3. Copy the new bot token and point it in the controller.
4. Specify the admin chat ID in the same place to receive service messages.
5. Copy the controller of the version you want to your yii framework
6. To work you need redis as a data store
7. The admin initializes the bot with the command /init 21 where 21 is the number of future participants in the secret Santa drawing
8. Next, each participant is invited to find a bot named santa0512 and send him the /start command