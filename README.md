# MinimalExample

## Installation

To install and enable the MinimalExample extension:

* Add the extension files to `extensions/MinimalExample`
* Add the following line to your LocalSettings.php:

`wfLoadExtension( 'MinimalExample' );`

* In the root directory of your wiki, make sure that `composer.local.json` is
set to include the dependencies from MinimalExample, e.g. with

```json
{
	"extra": {
		"merge-plugin": {
			"include": [
				"extensions/MinimalExample/composer.json"
			]
		}
	}
}
```

and then run `composer update` or `composer update --no-dev` to install the
dependencies.
