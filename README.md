# SilverStripe CLI

SilverStripe CLI is in development. Feel free to try it but be aware that most features aren't built and those that are are not stable and may change without notice.

## Installation

```
composer require -g micmania1/silverstripe-cli
```

## Why?

### Standardisation

One goal is to standardise development environments, code generation, module development and theme development. The project aims to provide value in high quality build tools for themes, promote best practice for module development and provide consistent re-producable environments which can be maintained after the project has been completed or handed over.

### Developers should be developing

When setting up a development environment, developers have a lot of choices to make. All the cool kids use docker, but where do you get started? What's this vagrant thing all about? Maybe WAMP or MAMP is easiest? Then when you choose you have to spend time setting it up; apache or nginx. Why can't I write to the assets folder? Why is this so hard?

When you finally have your dev environment setup you've wasted hours, if not days and it still only just works. When a new developer picks up your project, how will you have what you've just done with them?

The tool aims to make development environments a non-issue. Simply run `silverstripe env:up` and you have a re-producable environment which can run cross-platform and is optimized for the best efficiency during development.

### New project fatigue

Every time you start a new project there's a new build tool you want to try. You may spend time copying and pasting between project to mash together something that kind of works. Its all time wasted at the start of a project doing a task that you do every time you start a new project. SilverStripe Cli aims to not only have zero-setup time, but also provide  build tools with all of the bells and whistles, promotes best practice and enable you to hit the ground running.


## Contributing

There is a trello board up and running here: https://trello.com/b/CysUZuDK/silverstripe-cli

More tasks will be added over time.

## Commit Messages

Your commit messages should follow Angular's Commit Message Guideline: https://github.com/angular/angular.js/blob/master/CONTRIBUTING.md#commit

## Contact

**Developer** Michael Strong <mstrong@silverstripe.org>
