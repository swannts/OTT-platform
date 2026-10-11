import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_blurhash/flutter_blurhash.dart';
import 'package:http/http.dart' as http;
import 'package:swann_ott/configs.dart';
import 'package:swann_ott/components/shimmer_widget.dart';

class ImageBlurHashCache {
  ImageBlurHashCache._();

  static final Map<String, String> _hashes = {};
  static final Map<String, Future<String?>> _pending = {};
  static final Set<String> _unavailable = {};

  static Future<String?> load(String imageUrl) {
    final cachedHash = _hashes[imageUrl];
    if (cachedHash != null) return Future.value(cachedHash);
    if (_unavailable.contains(imageUrl)) return Future.value(null);
    return _pending.putIfAbsent(imageUrl, () => _load(imageUrl));
  }

  static Future<String?> _load(String imageUrl) async {
    try {
      final uri = Uri.parse('${BASE_URL}v3/image-blurhash')
          .replace(queryParameters: {'url': imageUrl});
      final response = await http.get(uri, headers: {
        'Accept': 'application/json'
      }).timeout(const Duration(seconds: 8));
      if (response.statusCode != 200) {
        if (response.statusCode == 404 || response.statusCode == 422) {
          _unavailable.add(imageUrl);
        }
        return null;
      }

      final body = jsonDecode(response.body);
      final hash = body is Map<String, dynamic> ? body['blur_hash'] : null;
      if (hash is! String || hash.isEmpty) {
        _unavailable.add(imageUrl);
        return null;
      }

      _hashes[imageUrl] = hash;
      if (_hashes.length > 500) _hashes.remove(_hashes.keys.first);
      return hash;
    } catch (_) {
      return null;
    } finally {
      _pending.remove(imageUrl);
    }
  }
}

class BlurHashPlaceholder extends StatefulWidget {
  final String imageUrl;
  final double? height;
  final double? width;
  final Widget? fallback;

  const BlurHashPlaceholder(
      {super.key,
      required this.imageUrl,
      this.height,
      this.width,
      this.fallback});

  @override
  State<BlurHashPlaceholder> createState() => _BlurHashPlaceholderState();
}

class _BlurHashPlaceholderState extends State<BlurHashPlaceholder> {
  late Future<String?> _hashFuture;

  @override
  void initState() {
    super.initState();
    _hashFuture = ImageBlurHashCache.load(widget.imageUrl);
  }

  @override
  void didUpdateWidget(covariant BlurHashPlaceholder oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.imageUrl != widget.imageUrl) {
      _hashFuture = ImageBlurHashCache.load(widget.imageUrl);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String?>(
      future: _hashFuture,
      builder: (context, snapshot) {
        final hash = snapshot.data;
        if (hash != null) {
          return SizedBox.expand(
            child: BlurHash(
                hash: hash,
                optimizationMode: BlurHashOptimizationMode.approximation),
          );
        }
        return widget.fallback ??
            ShimmerWidget(height: widget.height, width: widget.width);
      },
    );
  }
}

class BlurHashNetworkImage extends StatefulWidget {
  final String url;
  final double? height;
  final double? width;
  final BoxFit? fit;
  final AlignmentGeometry? alignment;
  final Color? color;
  final Widget? errorWidget;

  const BlurHashNetworkImage(
      {super.key,
      required this.url,
      this.height,
      this.width,
      this.fit,
      this.alignment,
      this.color,
      this.errorWidget});

  @override
  State<BlurHashNetworkImage> createState() => _BlurHashNetworkImageState();
}

class _BlurHashNetworkImageState extends State<BlurHashNetworkImage> {
  late Future<String?> _hashFuture;

  @override
  void initState() {
    super.initState();
    _hashFuture = ImageBlurHashCache.load(widget.url);
  }

  @override
  void didUpdateWidget(covariant BlurHashNetworkImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.url != widget.url) {
      _hashFuture = ImageBlurHashCache.load(widget.url);
    }
  }

  Widget _placeholder() {
    return FutureBuilder<String?>(
      future: _hashFuture,
      builder: (context, snapshot) {
        final hash = snapshot.data;
        if (hash != null) {
          return SizedBox.expand(
            child: BlurHash(
              hash: hash,
              optimizationMode: BlurHashOptimizationMode.approximation,
            ),
          );
        }
        return widget.errorWidget ??
            ShimmerWidget(height: widget.height, width: widget.width);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Image.network(
      widget.url,
      height: widget.height,
      width: widget.width,
      fit: widget.fit ?? BoxFit.cover,
      alignment: widget.alignment ?? Alignment.center,
      color: widget.color,
      webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return _placeholder();
      },
      errorBuilder: (_, __, ___) => _placeholder(),
    );
  }
}
