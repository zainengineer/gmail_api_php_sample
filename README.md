* First create application
  * https://console.developers.google.com/flows/enableapi?apiid=gmail
  * Choose web application
  * In authorized urls add
    * http://gmail.local.com    
* After applicaton creation and setup download its json
* move it to ~/.credentials/client_secret.json
* Make sure you setup a local domain like gmail.local.com (hard coded in my example code)


Setup js
* Run something like `php index.php > output.txt`
* open gmail developer console
* copy js from `manual_execute.js`
* copy js from output.txt
* use keyboard shortcut of `Ctr + .`
* select all keyboard shortcut *a