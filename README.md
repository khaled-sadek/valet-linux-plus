<p align="center"><img width="500" src="art/logo.png"></p>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/khaledsadek/valet-linux-plus.svg?style=flat-square)](https://packagist.org/packages/khaledsadek/valet-linux-plus)
[![Total Downloads](https://img.shields.io/packagist/dt/khaledsadek/valet-linux-plus.svg?style=flat-square)](https://packagist.org/packages/khaledsadek/valet-linux-plus)
[![PHP Version](https://img.shields.io/packagist/php-v/khaledsadek/valet-linux-plus.svg?style=flat-square)](https://packagist.org/packages/khaledsadek/valet-linux-plus)
[![Laravel Version](https://img.shields.io/badge/Laravel-11+-FF2D20?style=flat-square&logo=laravel)](https://packagist.org/packages/khaledsadek/valet-linux-plus)
[![License](https://img.shields.io/packagist/l/khaledsadek/valet-linux-plus.svg?style=flat-square)](https://packagist.org/packages/khaledsadek/valet-linux-plus)

## Introduction

> **Note:** This is a community-maintained fork of the original [genesisweb/valet-linux-plus](https://github.com/genesisweb/valet-linux-plus). Full credit goes to the original authors (Uttam Rabadiya, Divyank Munjapara) for building this amazing tool.

Valet *Linux+* is an advanced development environment for Linux minimalists. No Vagrant, no `/etc/hosts` file. You can even share your sites publicly using local tunnels. _Yeah, we like it too._

Valet *Linux+* configures your system to always run Nginx in the background when your machine starts. Then, using [DnsMasq](https://en.wikipedia.org/wiki/Dnsmasq), Valet proxies all requests on the `*.test` domain to point to sites installed on your local machine.

In other words, a blazing fast PHP development environment that uses roughly 7mb of RAM. Valet *Linux+* isn't a complete replacement for Vagrant or Homestead, but provides a great alternative if you want flexible basics, prefer extreme speed, or are working on a machine with a limited amount of RAM.

## Official Documentation

Documentation for Valet can be found on the [Valet Linux website](https://valetlinux.plus/).

## License

Laravel Valet is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
