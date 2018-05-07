# img.ragtag.tech

PHP app for resizing and optimizing images stored on S3.

## Basic URL Construction

Each image URL consists of 3 parts: the host, the partner slug and the asset path:

| host                    | partner slug | path                               |
| ----------------------- | ------------ | ---------------------------------- |
| https://img.ragtag.tech | assets       | examples/opengraph-share-image.png |

The `partner slug` tells the app which S3 bucket the `asset path` can be found in. Without any additional query parameters, the app will return the original image after it has been [optimized by one of several available optimization libraries](https://murze.be/easily-optimize-images-using-php-and-some-binaries).

## Query Parameters for Resizing and Transforming

| parameter | default | description                                                                                                                                                                                                                                                          | valid values                   |
| --------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ |
| e         | TRUE    | If true, images will be enlarged to fit the specified width and/or height if the image is smaller than the destination size. If false, automatic upscaling for high resolution screens is also disabled.                                                             | TRUE, true, 1, FALSE, false, 0 |
| w         | NULL    | The width, as an integer, to resize the image to.                                                                                                                                                                                                                    | any positive integer           |
| h         | NULL    | The height, as an integer, to resize the image to.                                                                                                                                                                                                                   | any positive integer           |
| fit       | crop    | If set to `crop`, the image will be resized to fit the exact width and height specified with any excess being removed from the outer edges. If set to `auto`, the image will be resized to fit within the specified width and height but may be shorter on one side. | crop, auto                     |
| fm        | NULL    | If set the image will be output in the specified format. Unsupported formats will be ignored.                                                                                                                                                                        | jpeg, jpg, png, gif            |

If only `w` or only `h` are set, the image's original aspect ratio will be preserved while being resized to the specified dimension.

## Support for High Resolution Displays

By including the JavaScript snippet below in the `<head>` of your pages, the app will automatically multiply the width and/or height parameters by the `devicePixelRatio` for each viewer.

```html
<script>if(window.devicePixelRatio && window.devicePixelRatio > 1){var s=document.createElement("script");s.src="https://img.ragtag.tech/dpr/?dpr="+Math.ceil(Math.max(1,window.devicePixelRatio));var h=document.getElementsByTagName("script")[0];h.parentNode.insertBefore(s,h)}</script>
```

## Examples

Our example image is https://assets.ragtag.tech/examples/bo.jpg. The original is 1920x2880 pixels with a filesize of 1,114KB (1.1MB).

### Original image optimized

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo.jpg)

New filesize of 954KB is a 14% size reduction.

### Resizing to fixed width

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo.jpg?w=300)

New dimensions of 300x450 retain the original's 2:3 aspect ratio.

### Resizing to fixed height

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo.jpg?h=300)

New dimensions of 200x300 retain the original's 2:3 aspect ratio.

### Resizing to fixed width and height

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo.jpg?w=300&h=300)

New dimensions of 300x300 cropped to the center of the photo by default.

### Resizing to fit within a width and height

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo.jpg?w=300&h=300&fit=auto)

New dimensions of 200x300 retain the original's 2:3 aspect ratio within the size given.

### Enlarging a smaller photo

For this one, our original is https://assets.ragtag.tech/examples/bo-small.jpg. The original is 200x300 pixels.

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo-small.jpg?w=1024&h=768)

As you can see, this is pretty pixelated. You can prevent the upscaling with the `e` parameter.

![Bo the dog on the White House Lawn](https://ragtag-images.herokuapp.com/assets/examples/bo-small.jpg?w=1024&h=768&e=0)

But that changes both the aspect ratio and prevents automatic support for high resolution screens.
