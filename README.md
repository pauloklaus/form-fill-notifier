# Form Fill Notifier

API to send the data filled in an HTML form to a pre-configured email.

## Installation

* You need [composer.phar](https://getcomposer.org).

1. Clone the project:

`git clone https://github.com/pauloklaus/form-fill-notifier`

2. Run composer update:

`php composer.phar update`

3. Configure the .env.php file.

## Test

1. At the root of project, run:

`php -S localhost:8080 -t public`

2. Send a request with the data:

`curl --request POST --data '{"origin": "my-personal-website", "name": "Paulo", "email": "my@email.com", "phone": "+55 11 0000-0000", "comments": "My test."}' http://localhost:8080/contact`

If everything works:

`{"message":"Success."}`

Now just make an HTML form and send the data in JSON format.

If you found an error, have any questions or can improve something, please call me.
