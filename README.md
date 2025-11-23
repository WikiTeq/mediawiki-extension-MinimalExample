# MarkdownPages

## Installation

To install and enable the MarkdownPages extension:

* Add the extension files to `extensions/MarkdownPages`
* Add the following line to your LocalSettings.php:

`wfLoadExtension( 'MarkdownPages' );`

* In the root directory of your wiki, make sure that `composer.local.json` is
set to include the dependencies from MarkdownPages, e.g. with

```json
{
	"extra": {
		"merge-plugin": {
			"include": [
				"extensions/MarkdownPages/composer.json"
			]
		}
	}
}
```

and then run `composer update` or `composer update --no-dev` to install the
dependencies.
