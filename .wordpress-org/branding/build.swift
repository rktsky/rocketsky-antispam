#!/usr/bin/env swift
/**
 * Builds the WordPress.org directory identity from the plugin's existing a. mark.
 *
 * Typography is converted to paths with Core Text. Published SVG files therefore
 * need no installed font, network connection or embedded font resource. The
 * unmodified local Roboto source and its SIL OFL licence stay beside this file.
 *
 * Run from the repository root: swift .wordpress-org/branding/build.swift
 * Raster export: see .wordpress-org/branding/README.md.
 */
import Foundation
import CoreGraphics
import CoreText

// Use Core Graphics' stable C entry point on macOS releases predating the overlay.
@_silgen_name("CGPathAddPath")
func addNativePath(_ path: CGMutablePath, _ transform: UnsafePointer<CGAffineTransform>?, _ appended: CGPath)

let root = URL(fileURLWithPath: FileManager.default.currentDirectoryPath)
let source = root.appendingPathComponent(".wordpress-org/branding")
let assets = root.appendingPathComponent(".wordpress-org/assets")
let fontURL = source.appendingPathComponent("fonts/Roboto-variable.ttf")
let ink = "#17142d"
let lime = "#d4ff52"
let paper = "#fffefa"

var registrationError: Unmanaged<CFError>?
guard CTFontManagerRegisterFontsForURL(fontURL as CFURL, .process, &registrationError) else {
	fatalError("Could not register the licensed local Roboto font: \(String(describing: registrationError))")
}

/** Returns an explicit local font instance at the requested variable weight. */
func font(_ size: CGFloat, weight: CGFloat) -> CTFont {
	let variation = [NSNumber(value: 2003265652): NSNumber(value: Double(weight))] // OpenType wght axis.
	let descriptor = CTFontDescriptorCreateWithAttributes([
		kCTFontNameAttribute: "Roboto-Regular",
		kCTFontVariationAttribute: variation,
	] as CFDictionary)
	let result = CTFontCreateWithFontDescriptor(descriptor, size, nil)
	precondition((CTFontCopyFamilyName(result) as String) == "Roboto", "No system-font fallback is permitted.")
	return result
}

/** Creates one path using native glyph shaping, preserving accented locale text. */
func textPath(_ value: String, size: CGFloat, weight: CGFloat, tracking: CGFloat = 0) -> CGPath {
	let typeface = font(size, weight: weight)
	let attributes: [NSAttributedString.Key: Any] = [
		NSAttributedString.Key(kCTFontAttributeName as String): typeface,
		NSAttributedString.Key(kCTKernAttributeName as String): tracking,
	]
	let line = CTLineCreateWithAttributedString(NSAttributedString(string: value, attributes: attributes))
	let path = CGMutablePath()
	for case let run as CTRun in CTLineGetGlyphRuns(line) as NSArray {
		let count = CTRunGetGlyphCount(run)
		var glyphs = [CGGlyph](repeating: 0, count: count)
		var positions = [CGPoint](repeating: .zero, count: count)
		CTRunGetGlyphs(run, CFRangeMake(0, 0), &glyphs)
		CTRunGetPositions(run, CFRangeMake(0, 0), &positions)
		let runFont = (CTRunGetAttributes(run) as NSDictionary)[kCTFontAttributeName] as! CTFont
		for index in 0..<count {
			guard let glyph = CTFontCreatePathForGlyph(runFont, glyphs[index], nil) else { continue }
			var transform = CGAffineTransform(translationX: positions[index].x, y: positions[index].y)
			addNativePath(path, &transform, glyph)
		}
	}
	return path
}

/** Serializes native outlines to compact, deterministic SVG path commands. */
func pathData(_ path: CGPath) -> String {
	func number(_ value: CGFloat) -> String {
		String(format: "%.3f", Double(value)).replacingOccurrences(of: #"\.?0+$"#, with: "", options: .regularExpression)
	}
	func point(_ value: CGPoint) -> String { "\(number(value.x)) \(number(value.y))" }
	var commands: [String] = []
	path.applyWithBlock { pointer in
		let item = pointer.pointee
		switch item.type {
		case .moveToPoint: commands.append("M\(point(item.points[0]))")
		case .addLineToPoint: commands.append("L\(point(item.points[0]))")
		case .addQuadCurveToPoint: commands.append("Q\(point(item.points[0])) \(point(item.points[1]))")
		case .addCurveToPoint: commands.append("C\(point(item.points[0])) \(point(item.points[1])) \(point(item.points[2]))")
		case .closeSubpath: commands.append("Z")
		@unknown default: fatalError("Unsupported native outline command.")
		}
	}
	return commands.joined(separator: " ")
}

/** Places an outlined text line by its baseline, with no runtime text node. */
func text(_ value: String, x: CGFloat, baseline: CGFloat, size: CGFloat, weight: CGFloat, fill: String, tracking: CGFloat = 0, maximumWidth: CGFloat = 0) -> String {
	let path = textPath(value, size: size, weight: weight, tracking: tracking)
	if maximumWidth > 0 { precondition(path.boundingBoxOfPath.width <= maximumWidth, "Line exceeds its reserved layout: \(value)") }
	return "<path fill=\"\(fill)\" transform=\"translate(\(x) \(baseline)) scale(1 -1)\" d=\"\(pathData(path))\"/>"
}

/** Keeps the double-storey a and full stop optically centred as one existing mark. */
func mark(cx: CGFloat, cy: CGFloat, radius: CGFloat) -> String {
	let path = textPath("a.", size: radius * 1.67, weight: 750, tracking: -radius * 0.015)
	let bounds = path.boundingBoxOfPath
	let x = cx - bounds.midX
	let baseline = cy + bounds.midY
	return """
	<circle cx="\(cx)" cy="\(cy)" r="\(radius)" fill="\(lime)"/>
	<path fill="\(ink)" transform="translate(\(x) \(baseline)) scale(1 -1)" d="\(pathData(path))"/>
	"""
}

/** Writes an accessible standalone SVG; all visible typography is outlined. */
func writeSVG(_ name: String, width: Int, height: Int, title: String, description: String, contents: String, destination: URL) throws {
	let svg = """
	<?xml version="1.0" encoding="UTF-8"?>
	<!-- Original Rocketsky Antispam identity. Generated by branding/build.swift. -->
	<svg xmlns="http://www.w3.org/2000/svg" width="\(width)" height="\(height)" viewBox="0 0 \(width) \(height)" role="img" aria-labelledby="title description">
	<title id="title">\(title)</title>
	<desc id="description">\(description)</desc>
	\(contents)
	</svg>

	"""
	try svg.write(to: destination.appendingPathComponent(name), atomically: true, encoding: .utf8)
}

try FileManager.default.createDirectory(at: assets, withIntermediateDirectories: true)
try writeSVG("icon.svg", width: 256, height: 256,
	title: "Rocketsky Antispam",
	description: "The plugin's ink a and full stop inside a lime circle.",
	contents: "<rect width=\"256\" height=\"256\" fill=\"\(ink)\"/>\n" + mark(cx: 128, cy: 128, radius: 108),
	destination: assets)

// Locale wording follows the plugin's own description rather than adding claims.
let copy: [(locale: String, line1: String, line2: String)] = [
	("", "Free local spam protection.", "No added CAPTCHA or external spam service."),
	("de", "Kostenloser lokaler Spamschutz.", "Ohne CAPTCHA oder externen Spamdienst."),
	("fr", "Protection antispam locale gratuite.", "Sans CAPTCHA ajouté ni service antispam externe."),
	("it", "Protezione antispam locale gratuita.", "Senza CAPTCHA né servizi antispam esterni."),
	("es", "Protección antispam local y gratuita.", "Sin CAPTCHA ni servicio antispam externo."),
]
for item in copy {
	let suffix = item.locale.isEmpty ? "" : "-\(item.locale)"
	let contents = """
	<rect width="772" height="250" fill="\(ink)"/>
	\(text("Rocketsky", x: 44, baseline: 64, size: 27, weight: 500, fill: paper, tracking: -0.7))
	\(text("Antispam", x: 40, baseline: 127, size: 65, weight: 750, fill: paper, tracking: -2.0))
	\(text(item.line1, x: 44, baseline: 179, size: 19, weight: 600, fill: lime, maximumWidth: 490))
	\(text(item.line2, x: 44, baseline: 207, size: 15.5, weight: 400, fill: paper, maximumWidth: 490))
	\(mark(cx: 653, cy: 125, radius: 85))
	"""
	try writeSVG("banner\(suffix).svg", width: 772, height: 250,
		title: "Rocketsky Antispam",
		description: "\(item.line1) \(item.line2)",
		contents: contents, destination: source)
}
print("Built outlined icon and 5 localized banner sources. Native line-width checks passed.")
