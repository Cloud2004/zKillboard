###FAQ

***

#####Can you remove a kill from zKillboard because ____________ ?

Kills are never removed from zKillboard. Once zKillboard has a killmail it is disseminated to about 30 different other sources and this number is always increasing. Removing a killmail will not prevent the fact that it happened. Even if CCP reimbursed your ship and items, the killmail still happened, and it still exists in game too.

***

#####I have NPC killmails on here, I don't want them here. Can you remove them?

In the Spring of 2016 zKillboard starting displaying all killmails that it receives. This has been a popular request and supported by many. Killmails aren't removed from zKillboard. Also, please remember that losses to just NPC's are not counted against you in your stats.

***

#####zKillboard doesn't have all my killmails, where are they?

zKillboard does not get all killmails automatically. They must be provided by various means. For some details, please read this Reddit post: https://www.reddit.com/r/Eve/comments/4m8jgm/zkillboard_does_not_automatically_get_all/

In short there are many sources for a killmail:

* API (ESI) submission
* War killmail (victim and final blow have a Concord sanctioned war with each other)
* API given by victim
* API given by final blow character
* API given by victim's corporation
* API given by final blow character's corporation

Remember, every PVP killmail has two sides, the victim and the aggressors. Victims often don't want their killmails to be made public, however, the aggressors do. 

***

#####I don't like the points that are given on a particular kill, can you fix it to appease me?

No. Points are very, very arbitrary. Calculating them in a fashion that keeps everyone happy is impossible.

In short, this is how points are calculated:

* Size of victim ship
* Meta level of items fitted that have offensive/defensive capabilities to determine danger level of victim.
* Meta level of Miners fitted to reduce points.
* Number involved on killmail, the larger the gang the bigger the penalty.
* Average size of attacking ships. Killing a bigger ship gets up to a 20% bonus, a smaller ship up to a 50% penalty.
* A kill is always worth at least 1 point.

Any attempts at point discussion will likely result in a link to this FAQ.

***

#####How do you value blueprint copies or skin prices?

Blueprints copies and skin prices have been extremely volatile and are difficult to calculate the proper price. Because of this difficulty, it is better to err on the side of low value and all blueprint copies and skins are given a price of 0.01 ISK.

***

#####Why are blueprints in a container always copies, those were originals in the killmail I got!

There is a longstanding bug in killmails that always show a blueprint as an original when it is inside of a container, wrapped, etc. Therefore, rather than value the blueprint at the full BPO value and created some crazy prices on killmails, zKillboard errs on the side of caution and makes all container'ed blueprints as BPCs and prices them accordingly.

***

#####I have given you my XML API or logged in with SSO, what are you doing with this information?

zKillboard will only read the killmails portions of the XML API and SSO, if provided with the proper permissions. Submission of the XML API to zKillboard is determined as having given permission, regardless of the source/person submitting the API. zKillboard will only read killmails from the SSO login only if the "Allow zKillboard to read your Kills" is checked when logging in.

XML APIs that give more explicit permissons than just access to KillMails will still be used for only reading KillMails. All other portions of the XML API are ignored and remain unused.

zKillboard will write to your character's ship fittings via SSO & CREST, if and only if, it has been given permission to do so by having the "Allow zKillboard to write Fittings" checked when logging in and you click a "Save Fitting" link from a killmail page.

Audit logs should show an IP address with an md5 hash of: 8b084aa0f07098edbcb72667e39a7600

This site can be used to quickly calculate hashes: http://www.miraclesalad.com/webtools/md5.php

***

#####I don't want my character/corporation/alliance shown on zKillboard? How do I remove them?

All characters, corporations, and alliances will always be displayed. zKillboard will not accept ISK or any form of currency to have entities removed. Yes, offers have been made and all offers get turned down.

***

#####You are violating my privacy rights! I will sue! What will you do to stop?

Nothing. You are throwing threats at a game website where the ships, names, killmails, etc. are all owned by CCP. While this website is not owned or operated by CCP it does derive all of its information from their databases. 

If for some reason a character's name matches your real life name, please, contact CCP and work with them to find a resolution. 
https://www.ccpgames.com/contact-us/

Please also read and understand Section 230 of the Communications Decency Act: https://www.eff.org/issues/cda230

***

#####I don't like you and I want all my API's gone! How do I remove them?

There is a page within your account for that, please visit https://zkillboard.com/account/api/ (You must be logged in.)

Also, CCP provides the following pages to help manage APIs:

* XML API: https://community.eveonline.com/support/api-key/
* SSO API: https://community.eveonline.com/support/third-party-applications/
