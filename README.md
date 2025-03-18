# Craft CMS Cloudflare Images

> This plugin offers a easy way to upload your images assets to Cloudflare Images from Craft CMS assets interface.
> It exposes the a file system and supports craft image transforms.

Installation:

1. Install with composer

```sh
composer require deuxhuithuit/craft-cloudflare-images
```

2. Install in craft

```sh
craft plugin/install cloudflare-images
```

3. Add your account id, account hash and api token in the settings. Those can be found in your
Cloudflare dashboard. We recommend to use env vars for this.

4. Go in craft and create a new File System using the Cloudflare Image FS. Also create a new Volume
that uses this FS. It can be used as the main FS, or simply as a transform FS in an existing Volume.

5. Make sure Flexible Variants are enabled in your Cloudflare dashboard
(see https://developers.cloudflare.com/images/cloudflare-images/transform/flexible-variants/).
By default, Cloudflare Images do not generate publicly accessible images. Enabling flexible variants
will generate publicly accessible images and allow for craft image transform to work. The public url
is accessible via the `$asset->cloudflareImagesUrl()` method. If flexible variants are not enabled,
the image is only accessible via Craft's Control Panel.

6. Profit!

Made with ❤️ in Montréal.

(c) Deux Huit Huit
