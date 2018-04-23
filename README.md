# WPGraphQL Content Blocks
A [WPGraphQL](https://github.com/wp-graphql/wp-graphql) plugin that returns a WordPress post’s content as a shallow tree and allows for some limited validation and cleanup. This is helpful for rendering post content within component-based front-end ecosystems like React.

## What this plugin does
This plugin adds a GraphQL field called `blocks` to the WPGraphQL `post` field.

The `blocks` field contains a list of the root-level blocks that comprise the post's content. Each block is a distinct HTML element, embeddable URL, or shortcode.

For example, if a post’s `content` field in GraphQL contained:

```html
<p>Hello world</p>
<ul>
	<li>Here is a list</li>
</ul>
```

Then the `blocks` field would contain:

```json
[
	{
		"type": "P",
		"innerHtml": "Hello world"
	},
	{
		"type": "UL",
		"innerHTML": "<li>Here is a list</li>"
	}
]
```

## GraphQL fields and types
An exhaustive GraphQL of a post’s `blocks` field would look like this:

```graphql
blocks {
	type
	innerHtml
	attributes {
		name
		value
	}
}
```

This will return a list of `BlockTypes`, defined in`block-type.php`. Let’s break down each of these fields:

### `type`

Type: `BlockNameEnumType ` (defined in `block-name-enum-type.php`)

The name of the block. For HTML tags, this is the uppercase version of the HTML tag name, e.g. `P`, `UL`, `BLOCKQUOTE `, `TABLE` etc.. Shortcode and embed BlockNames are name-spaced with `SHORTCODE_` and `EMBED_` respectively., e.g. `SHORTCODE_CAPTION`,  `EMBED_INSTAGRAM ` etc. 

Permissible block names are registered in `BlockDefinitions::$definitions` (`block-definitions.php`). HTML types are specified here manually, embed types are determined by the handlers that have been registered in `$wp_embed->handlers`, and shortcodes are determined by the handlers registered with `$shortcode_tags`. A complete list of permissible block names can seen by browsing the GraphQL schema.

### `innerHtml`

Type: `String`

The stringified content of the block. Can be passed into a React component using `dangerouslySetInnerHTML`, for example.

Note that the value of `innerHtml` is the stringified version of all the block’s descendants *after* they have been parsed. This means that any invalid tags, attributes, etc. will have been stripped out.

### `attributes`

Type: List of`BlockAttributeType` arrays (defined in `block-attribute-type.php`)

Each item in the list is an associative array containing a key/value pair for an attribute of the block. For HTML blocks, these are taken from the HTML attributes, e.g.

```json
{
	"name": "style",
	"value":  "color: red"
}
```

Note that this field will only contain valid attributes for the given Block, as defined in `BlockDefinitions`. Invalid attributes are stripped out during the parsing process (see [How Parsing Works](#how-parsing-works)).

## Blocks
### What is a block?

A block is an atomic piece of content that comprises an article body. We define a block as being an HTML tag (like a paragraph, list, table, etc), a text node (the textual content of a non-empty HTML element or shortcode), a [shortcode](https://codex.wordpress.org/shortcode) (like a caption, gallery, or video) or an [embed](https://codex.wordpress.org/Embeds) (an embeddable URL on its own line in the post content).

### HTML Blocks

An HTML block is an HTML element (typically a  [block-level](https://developer.mozilla.org/en-US/docs/Web/HTML/Block-level_elements) element) represented by its tag name. An HTML block must exist in `BlockDefinitions::definitions` in order to be included (see Block Definitions below). If an HTML tag’s name does not have a key in `BlockDefinitions::definitions`, it will be stripped from the tree. Additionally, at runtime it must meet the requirements specified in order to be considered valid. 

An example HTML block in a GraphQL response looks like this:

```json
{
	"type": "P",
	"innerHtml": "This isn&#x2019;t the first time Facebook has unveiled new privacy settings in response to user concerns. It debuted a redesign that promised to give users more control over their data <a href=\"https://www.theguardian.com/technology/2010/may/26/facebook-new-privacy-controls-data\">back in 2010</a>.&#xC2;&#xA0;&#x201C;People think that we don&#x2019;t care about privacy, but that&#x2019;s not true,&#x201D; Zuckerberg said at the time. Yet some observers, including Quartz reporter Mike Murphy, remain skeptical.",
	"attributes": []
}
```

Note that the contents of `innerHtml` are stringified and HTML encoded, ready to be rendered with [`Element.innerHTML`](https://developer.mozilla.org/en-US/docs/Web/API/Element/innerHTML) (if you're using the Javascript DOM API)  or[`Component.dangerouslySetInnerHTML`](https://reactjs.org/docs/dom-elements.html#dangerouslysetinnerhtml) (if you're using React), etc.

### Shortcode and Embed Blocks

A shortcode block is a WordPress shortcode. *Shortcode/Embed blocks are returned unparsed* - the parsing of shortcodes is the responsibility of the front-end consuming the GraphQL endpoint. Only the name of the shortcode, its attributes and any nested content of the shortcode are returned in the GraphQL response.

Shortcode block type names are prefixed with the `SHORTCODE_`  namespace by default.

An example shortcode block looks like this:

```json
{
	"type": "SHORTCODE_PULLQUOTE",
	"innerHtml": "The prosaic truth is that historical context determines how elites are judged at any moment.",
	"attributes": []
}
```

HTML that is nested within shortcode tags should remain unescaped, like this:

```json
{
	"type": "SHORTCODE_PULLQUOTE",
	"innerHtml": "Here is some <abbr title=\"HyperText Markup Language\">HTML</abbr> within a shortcode",
	"attributes": []
},
```

An embed is a distinct block-type that represents WordPress’ [URL-to-markup embedding functionality](https://codex.wordpress.org/Embeds). Wherever a URL exists by itself in a root-level <p> tag, and if that URL is of a supported embed type, the URL will be represented as an embed block.

Embed block type names are prefixed with the `EMBED_`  namespace by default.

An example embed block in a GraphQL response looks like this:

```json
{
	"type": "EMBED_TWITTER",
	"innerHtml": "",
	"attributes": [
		{
			"name": "url",
			"value": "https://twitter.com/mcwm/status/978975850455556097"
		}
	]
}
```

Because neither shortcode or embed blocks are parsed, the markup for embedding the URL is not provided by the plugin.

### Block definitions

We can specify the requirements for individual blocks. This allows us to enforce certain rules about blocks that determine where they end up in the tree, what attributes they may have, and whether or not they should end up in the GraphQL response at all.

Definitions for blocks can be found in `block-definitions.php`.

The default definition for a block is:

```php
[
	// Should the block be preserved if it has no content / children?
	'allow_empty' => false,
	'attributes'  => [
		// An array of attributes to allow. All other attributes are removed.
		'allow'    => null,
		// An array of attributes to remove (blacklist). All other attributes
		// are allowed. Ignored if `allow` is provided.
		'deny'     => array( 'class' ),
		// A list of required attributes. If `null`, no attributes are required.
		// Processing occurs after allow/deny.
		'required' => null,
		// Require there to be at least one valid attribute of any kind for
		// the block to be valid. All attributes in the required array
		// must also be present.
		'require_one_or_more' => false,
	],
	// An array of regular expressions used to determine whether the node
	// matches (only for embeds and shortcodes).
	'regex' => array(),
	// Is permitted to exist at the root level of the tree? If false it will be wrapped in a <p> tag
	'allow_root' => true,
	// Must the block appear at the root-level of the tree? If true it will be hoisted out of its position in the tree and placed at the root level.
	'root_only' => true,
	// Override the type to a different value?
	'type' => null,
]
```

Each block can be given specific overrides to these defaults depending on user preference. These overrides are defined in `BlockDefinitions::$definitions`. 

These defaults suits us well for most block elements - we always want them to exist at the root and will hoist them to the root if we find them nested deeper in the post content HTML. We therefore don’t provide any overrides for most block elements:

```php
'blockquote' => [],
'figure' => [],
'h1' => [],
'h2' => [],
'h3' => [],
'h4' => [],
'h5' => [],
'h6' => [],
'hr' => [],
// etc
```

We permit `<p>` tags to live at the root, but we do not enforce it (i.e. we don’t want to hoist a `<p>` tag out of a parent element) so we use this definition.

```php
'p' => [
	'root_only' => false,
],
```

We don’t want inline HTML elements like `<a>` to exist by themselves at the root, and we don’t want to permit the `target` attribute, so we use the following definition:

```php
'a' => [
	'attributes' => array(
		'deny' => array( 'target' ),
	),
	'root_only' => false,
	'allow_root' => false,
],
```

Now any `<a>` tag found at the root-level of the post HTML will be wrapped in a `<p>` tag before being added to the tree. Additionally, if there is a `target` attribute, it will not appear in the `attribute` field.

## How parsing works
Here’s a rough breakdown of the process of parsing post content into blocks:

1. The post content string is prepared for parsing (see `Fields::prepare_html` in `fields.php`). This includes running the `wpautop`, `wptexturize` and `convert_chars` filters.
2. The prepared content string is loaded into a [PHP DOMDocument](http://php.net/manual/en/class.domdocument.php)) object. This allows us to recurse the HTML as a tree.
3. The `DOMDocument` object is passed into an `HTMLBlock` (`html-block.php`) object. This begins the process of recursing the tree. Each child block is assigned a class depending on its type: `HTMLBlock`, `TextBlock`, `EmbedBlock` or `ShortcodeBlock`. Each block is responsible for validating itself against the Block Definitions (`block-definitions.php`) to determine whether it belongs in the tree or not.
4. Although the tree is recursed and validated to an infinite depth, the GraphQL type `BlockType` will stringify the tree below a depth of 1 for consumption in the GraphQL endpoint.

## What about Gutenberg?
This project is predicated on the ability to switch to using Gutenberg blocks instead of custom parsing, once Gutenberg is released. We look forward to being Gutenberg-compatible!

## Examples
Given a query for the content of a post returns the following:

Query:

```graphql
{
	post(id: "cG9zdDoxMjM5NzIx") {
		content
	}
}
```

Response:

```json
{
	"data": {
		"post": {
			"content": "<p>Now this is a story all about how<br />\nMy life got flipped turned upside down<br />\nAnd I&#8217;d like to take a minute, just sit right there<br />\nI&#8217;ll tell you how I became the prince of a town called Bel-Air</p>\n<p>https://www.youtube.com/watch?v=AVbQo3IOC_A</p>\n<p>In West Philadelphia, born and raised<br />\nOn the playground is where I spent most of my days<br />\nChillin&#8217; out, maxin&#8217;, relaxin&#8217; all cool<br />\nAnd all shootin&#8217; some b-ball outside of the school<br />\nWhen a couple of guys who were up to no good<br />\nStarted makin&#8217; trouble in my neighborhood<br />\nI got in one little fight and my mom got scared<br />\nAnd said &#8220;You&#8217;re movin&#8217; with your auntie and uncle in Bel-Air</p>\n[pullquote]You&#8217;re movin&#8217; with your auntie and uncle in Bel-Air[/pullquote]\n<p>I begged and pleaded with her day after day<br />\nBut she packed my suitcase and sent me on my way<br />\nShe gave me a kiss and then she gave me my ticket<br />\nI put my Walkman on and said &#8220;I might as well kick it&#8221;<br />\nFirst class, yo, this is bad<br />\nDrinkin&#8217; orange juice out of a champagne glass<br />\nIs this what the people of Bel-Air livin&#8217; like?<br />\nHmmm, this might be all right<br />\nBut wait, I hear they&#8217;re prissy, bourgeois, and all that<br />\nIs this the type of place that they just sent this cool cat?<br />\nI don&#8217;t think so, I&#8217;ll see when I get there<br />\nI hope they&#8217;re prepared for the Prince of Bel-Air</p>\n<p><img src=\"https://example.com/fresh-prince.jpeg\" alt=\"The Fresh Prince of Bel Air\" /></p>\n"
		}
	}
}
```

Then we would expect a query for the blocks that comprise the post to return the following.

Query:

```graphql
{
	post(id: "cG9zdDoxMjM5NzIx") {
		blocks {
			type
			innerHtml
		}
	}
}
```

Response:

```json
{
	"data": {
		"post": {
			"blocks": [
				{
					"type": "P",
					"innerHtml": "Now this is a story all about how<br>My life got flipped turned upside down<br>And I&#x2019;d like to take a minute, just sit right there<br>I&#x2019;ll tell you how I became the prince of a town called Bel-Air"
				},
				{
					"type": "EMBED_YOUTUBE",
					"innerHtml": ""
				},
				{
					"type": "P",
					"innerHtml": "In West Philadelphia, born and raised<br>On the playground is where I spent most of my days<br>Chillin&#x2019; out, maxin&#x2019;, relaxin&#x2019; all cool<br>And all shootin&#x2019; some b-ball outside of the school<br>When a couple of guys who were up to no good<br>Started makin&#x2019; trouble in my neighborhood<br>I got in one little fight and my mom got scared<br>And said &#x201C;You&#x2019;re movin&#x2019; with your auntie and uncle in Bel-Air"
				},
				{
					"type": "SHORTCODE_PULLQUOTE",
					"innerHtml": "You&amp;#8217;re movin&amp;#8217; with your auntie and uncle in Bel-Air"
				},
				{
					"type": "P",
					"innerHtml": "I begged and pleaded with her day after day<br>But she packed my suitcase and sent me on my way<br>She gave me a kiss and then she gave me my ticket<br>I put my Walkman on and said &#x201C;I might as well kick it&#x201D;<br>First class, yo, this is bad<br>Drinkin&#x2019; orange juice out of a champagne glass<br>Is this what the people of Bel-Air livin&#x2019; like?<br>Hmmm, this might be all right<br>But wait, I hear they&#x2019;re prissy, bourgeois, and all that<br>Is this the type of place that they just sent this cool cat?<br>I don&#x2019;t think so, I&#x2019;ll see when I get there<br>I hope they&#x2019;re prepared for the Prince of Bel-Air"
				},
				{
					"type": "IMG",
					"innerHtml": ""
				}
			]
		}
	}
}
```

We can also see the attributes for the shortcode and embed blocks by requesting the `attributes` field.

Query:

```graphql
{
	post(id: "cG9zdDoxMjM5NzIx") {
		blocks {
			type
			attributes {
				name
				value
			}
		}
	}
}
```

Response:

```json
{
	"data": {
		"post": {
			"blocks": [
				{
					"type": "P",
					"attributes": []
				},
				{
					"type": "EMBED_YOUTUBE",
					"attributes": [
						{
							"name": "url",
							"value": "https://www.youtube.com/watch?v=AVbQo3IOC_A"
						}
					]
				},
				{
					"type": "P",
					"attributes": []
				},
				{
					"type": "SHORTCODE_PULLQUOTE",
					"attributes": []
				},
				{
					"type": "P",
					"attributes": []
				},
				{
					"type": "IMG",
					"attributes": [
						{
							"name": "src",
							"value": "https://example.com/fresh-prince.jpeg"
						},
						{
							"name": "alt",
							"value": "The Fresh Prince of Bel Air"
						}
					]
				}
			]
		}
	}
}
```
