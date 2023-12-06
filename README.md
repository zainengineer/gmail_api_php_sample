* composer install
* First create application
  * https://console.developers.google.com/flows/enableapi?apiid=gmail
    * Use recent Project "Gmail working 2023"
    * get credentials for gmail api oath client
  * Choose web application
  * In authorized urls add
    * http://gmail.local.com
    * Project needs to be in developer mode to use http
    * Need to access url using accounts allowed in developer mode
* After application creation and setup download its json
* move it to ~/.credentials/client_secret.json
* Make sure you setup a local domain like gmail.local.com (hard coded in my example code)

Execution
* `php index.php`
  * might need to run multiple times as it sets up different things in each step
* follow instructions

Misc
* go to git directory and run 
  * `sudo php -S localhost:80`
* in `/etc/hosts` make entry for `gmail.local.com`
* in local create `local.config.json` in it add config like `{"email":"youremail@gmail.com"}`


Setup js
* Run something like `php index.php > output.js`
* open gmail developer console
* copy js from `manual_execute.js`
* copy js from output.txt
* use keyboard shortcut of `Ctr + .`
* select all keyboard shortcut *a