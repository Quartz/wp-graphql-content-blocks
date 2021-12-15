# WPGraphQL Content Blocks (Structured Content)
This [WPGraphQL](https://github.com/wp-graphql/wp-graphql) plugin returns a WordPress post’s content as a shallow tree of blocks and allows for some limited validation and cleanup. This is helpful for rendering post content within component-based front-end ecosystems like React.

## What this plugin does
This plugin adds a GraphQL field called `blocks` to `Post` in WPGraphQL (and any other post types configured to appear in WPGraphQL).

The `blocks` field contains a list of the root-level blocks that comprise the post's content. Each block is a distinct HTML element, embeddable URL, shortcode, or Gutenberg block (beta!).

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

When consuming this field, you can now easily iterate over the blocks and map them to components in your component library. No more `dangerouslySetInnerHTML`ing your entire `post_content`!

## GraphQL fields and types
An exhaustive GraphQL of a post’s `blocks` field would look like this:

```graphql
blocks {
	type
	tagName
	innerHtml
	renderedHtml
	attributes {
		name
		value
		json
	}
	connections {
		... on Post {
			...PostParts
		}
		... on MediaItem {
			...MediaItemParts
		}
	}
}
```

This will return a list of `BlockTypes`, defined in `src/types/Blocktype.php`. Let’s break down each of these fields:

### `type`

Type: `BlockNameEnumType` (defined in `src/types/enums/BlockNameEnumType.php`)

The name of the block. For HTML blocks, this is the uppercase version of the HTML tag name, e.g. `P`, `UL`, `BLOCKQUOTE`, `TABLE` etc. Shortcode and embed types are name-spaced with `SHORTCODE_` and `EMBED_` respectively., e.g. `SHORTCODE_CAPTION`, `EMBED_INSTAGRAM`, etc.

HTML block types are hardcoded, as are Gutenberg block types (for now). Embed types are determined by the handlers that have been registered in `global $wp_embed->handlers` and shortcodes are determined by the handlers registered with `global $shortcode_tags`. A complete list of permissible block names can seen by browsing the WPGraphQL schema.

You can filter the type definitions with `graphql_blocks_definitions`.

### tagName

Type: `String`

The suggested HTML tag name for the block. For HTML blocks, this is simply a lowercased version of the type field. For embeds and shortcodes, it will likely be `null`. This field is most useful for Gutenberg blocks, as a hint from the server for which tag to use when wrapping the `innerHtml` (see below).

### `innerHtml`

Type: `String`

The stringified inner content of the block. Can be passed into a React component using `dangerouslySetInnerHTML`, for example.

Note that the value of `innerHtml` is the stringified version of all the block’s descendants *after* they have been parsed. This means that any invalid tags, attributes, etc. will have been stripped out.

### `renderedHtml`

Type: `String`

Rendered output for Gutenberg blocks.

### `attributes`

Type: List of `BlockAttributeType`s

Each item in the list is a name/value pair describing an attribute of the block. For HTML blocks, these are taken from the HTML attributes, e.g.

```json
{
	"name": "id",
	"value":  "section1",
	"json": false
}
```

If attribute is an array, it's encoded in output e.g.

```json
{
	"name": "id",
	"value":  "{\"section\":1}",
	"json": true
}
```

Note that this field will only contain valid attributes for the given Block, as defined in `BlockDefinitions`. Invalid attributes are stripped out during the parsing process (see [How Parsing Works](#how-parsing-works)).

### `connections` (beta)

Type: List of `MenuItemObjectUnion`s

The `connections` field returns an array of objects that are connected to the block. For example, if you wanted to upload an image and associate it with a block, that image could be queried as a GraphQL connection here. The `connections` field will **always be empty by default**. Presumably there is some way to derive these connections from the block's attributes, but we have no way of knowing what that correspondence is. If you'd like to use this field, it's up to you to filter `graphql_blocks_output` and populate the `connections` array as you see fit.

## Blocks
### What is a block?

A block is an atomic piece of content that comprises an article body. We define a block as being an HTML tag (like a paragraph, list, table, etc), a Gutenberg block, a text node (the textual content of a non-empty HTML element or shortcode), a [shortcode](https://codex.wordpress.org/shortcode) (like a caption, gallery, or video), or an [embed](https://codex.wordpress.org/Embeds) (an embeddable URL on its own line in the post content).

### HTML Blocks

An HTML block is an HTML element (typically a  [block-level](https://developer.mozilla.org/en-US/docs/Web/HTML/Block-level_elements) element) represented by its tag name. If an HTML tag’s name is not included in `BlockNameEnumType`, it will be stripped from the tree. Additionally, at runtime it must meet the requirements specified in the block definitions in order to be considered valid.

An example HTML block in a GraphQL response looks like this:

```json
{
	"type": "P",
	"innerHtml": "This isn’t the first time Facebook has unveiled new privacy settings in response to user concerns. It debuted a redesign that promised to give users more control over their data <a href=\"https://www.theguardian.com/technology/2010/may/26/facebook-new-privacy-controls-data\">back in 2010</a>. “People think that we don’t care about privacy, but that’s not true,” Zuckerberg said at the time. Yet some observers, including Quartz reporter Mike Murphy, remain skeptical.",
	"attributes": []
}
```

### Gutenberg blocks (beta)

Gutenberg blocks map very well to blocks, but do not have a server-side registration system. Like HTML blocks, we are forced to hardcode a list of core Gutenberg blocks. This list can be extended with `graphql_blocks_definitions` to add your own custom Gutenberg blocks.

Another issue with Gutenberg is that the markup of a (non-dynamic) block is defined only in JavaScript and then rendered directly into `post_content`. While we are most interested in the `attributes` of a block, the `innerHtml` is also important since the rendered tag name could be important information to, say, a React component tasked with its implementation.

For this reason, we descend into the `innerHtml` of a Gutenberg block to extract the `tagName` of the surrounding tag, then discard it, leaving just the true "inner" HTML of the block.

```json
{
	"type": "CORE_HEADING",
	"tagName": "h2",
	"attributes": [],
	"innerHtml": "My Heading",
	"renderedHtml": "<h2>My Heading</h2>",
	"parent_id": null
}
```

In the example above, this allows the `innerHtml` to be `My Heading` instead of `<h2>My Heading</h2>`. This is a much better situation for the components that implement this data.

Gutenberg blocks present a number of challenges and the spec is still evolving. Take care when using this plugin with Gutenberg blocks since there will likely be breaking changes ahead.

Nested blocks structure can be recreated by using `parent_id` field. There is probably a way to return proper blocks structure using GraphQL Fragments, but for now that solution should work.

### Shortcode and Embed Blocks

A shortcode block is a WordPress shortcode. *Shortcode/embed blocks are returned untransformed*: the parsing of shortcodes is the responsibility of the front-end consuming the GraphQL endpoint. Only the name of the shortcode, its attributes and any nested content of the shortcode are returned in the GraphQL response.

Shortcode block type names are prefixed with the `SHORTCODE_`  namespace by default.

```json
{
	"type": "SHORTCODE_PULLQUOTE",
	"innerHtml": "Here is some <abbr title=\"HyperText Markup Language\">HTML</abbr> within a shortcode",
	"attributes": []
}
```

An embed is a distinct block-type that represents WordPress’ [URL-to-markup embedding functionality](https://codex.wordpress.org/Embeds). If WordPress recognizes a URL as an embed, this plugin will output it as an embed block.

Embed block type names are prefixed with the `EMBED_`  namespace by default.

```json
{
	"type": "EMBED_TWITTER",
	"innerHtml": "",
	"attributes": [
		{
			"name": "url",
			"value": "https://twitter.com/mcwm/status/978975850455556097",
			"json": false
		}
	]
}
```

Because neither shortcode or embed blocks are parsed, the markup for embedding the URL is not provided by the plugin.

### Block definitions

We can specify validation requirements for individual blocks. This allows us to enforce certain rules about blocks that determine where they end up in the tree, what attributes they may have, and whether or not they should end up in the GraphQL response at all.

Block definitions (and the default definition from which all blocks extend) for blocks can be found in `src/types/shared/BlockDefinitions`. If you are interested in filtering the block definitions (via `graphql_blocks_definitions`) to override behavior or add your own block definitions, you should look over that file.

Our default block definition suits us well for most block HTML elements; we always want them to exist at the root and we will hoist them to the root if we find them nested deeper in the post content HTML. We therefore don’t provide any overrides for most block elements:

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
Here’s a rough breakdown of the process of parsing post content into blocks. (If the block is a Gutenberg block, we use `gutenberg_parse_blocks` and skip these steps.)

1. The post content string is prepared for parsing (see `Fields::prepare_html` in `src/data/Fields.php`). This includes running the `wpautop`, `wptexturize` and `convert_chars` filters.
2. The prepared content string is loaded into a [PHP DOMDocument](http://php.net/manual/en/class.domdocument.php)) object. This allows us to recurse the HTML as a tree.
3. The `DOMDocument` object is passed into an `HTMLBlock` (`src/parser/class-htmlblock.php`) object. This begins the process of recursing the tree. Each child block is assigned a class depending on its type: `HTMLBlock`, `TextBlock`, `EmbedBlock` or `ShortcodeBlock`. Each block is responsible for validating itself against the Block Definitions (`src/types/shared/BlockDefinitions.php`) to determine whether it belongs in the tree or not.
4. Although the tree is recursed and validated to an infinite depth, the GraphQL type `BlockType` (`src/types/BlockType.php`) will stringify the tree below a depth of 1 for consumption in the GraphQL endpoint.

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
					"innerHtml": "Now this is a story all about how<br>My life got flipped turned upside down<br>And I’d like to take a minute, just sit right there<br>I’ll tell you how I became the prince of a town called Bel-Air"
				},
				{
					"type": "EMBED_YOUTUBE",
					"innerHtml": ""
				},
				{
					"type": "P",
					"innerHtml": "In West Philadelphia, born and raised<br>On the playground is where I spent most of my days<br>Chillin’ out, maxin’, relaxin’ all cool<br>And all shootin’ some b-ball outside of the school<br>When a couple of guys who were up to no good<br>Started makin’ trouble in my neighborhood<br>I got in one little fight and my mom got scared<br>And said “You’re movin’ with your auntie and uncle in Bel-Air"
				},
				{
					"type": "SHORTCODE_PULLQUOTE",
					"innerHtml": "You’re movin’ with your auntie and uncle in Bel-Air"
				},
				{
					"type": "P",
					"innerHtml": "I begged and pleaded with her day after day<br>But she packed my suitcase and sent me on my way<br>She gave me a kiss and then she gave me my ticket<br>I put my Walkman on and said “I might as well kick it”<br>First class, yo, this is bad<br>Drinkin’ orange juice out of a champagne glass<br>Is this what the people of Bel-Air livin’ like?<br>Hmmm, this might be all right<br>But wait, I hear they’re prissy, bourgeois, and all that<br>Is this the type of place that they just sent this cool cat?<br>I don’t think so, I’ll see when I get there<br>I hope they’re prepared for the Prince of Bel-Air"
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
				json
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
							"value": "https://www.youtube.com/watch?v=AVbQo3IOC_A",
							"json": false                          
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
							"value": "https://example.com/fresh-prince.jpeg",
							"json": false
						},
						{
							"name": "alt",
							"value": "The Fresh Prince of Bel Air",
							"json": false
						}
					]
				}
			]
		}
	}
}
```
