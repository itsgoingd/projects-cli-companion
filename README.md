Projects CLI Companion
======================

**This repository contains a CLI tool for working with an internal projects management system, feel free to browse and
reuse the code, but the tool itself is not meant for public use.**

## Installation

You can download [latest version as an executable phar archive](http://abyss.shadowfall.eu/projects-cli-companion).

You need to set up the tool after downloading via `php projects-cli-companion.phar setup`.

## Usage

This tool allows you to easily use a modern version control system with our projects management server by creating a local
GIT repository and providing tools for keeping it synchronized with the remote SVN repository.

### Checkout

Use `checkout` command to check out a new project from remote SVN repository.

### Pull

Use `pull` command to pull in changes from remote SVN repository and import them to local GIT repository.

### Push

Use `push` command to push out changes from local GIT repository to remote SVN repository, you can specify the work time
as an argument. This command also respects `.gitignore` entries when commiting the files to SVN.

You can also reference a ticket in a commit message using `#ticket_number` (eg. `Fixed broken layout (#42).`) and the commit
message will be automatically posted as a comment in the ticket.

## Licence

Copyright (c) 2015 Miroslav Rigler

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
