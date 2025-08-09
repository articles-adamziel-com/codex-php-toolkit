<?php

namespace WordPress\Blueprints\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use stdClass;
use WordPress\Blueprints\Validator\HumanFriendlySchemaValidator;
use WordPress\Blueprints\Validator\UnsupportedSchemaException;
use WordPress\Blueprints\Validator\ValidationError;

/**
 * Tests for the HumanFriendlySchemaValidator class.
 * This class focuses on general JSON schema validation capabilities.
 */
class HumanFriendlySchemaValidatorTest extends TestCase {

	// Test Primitive Types
	public static function primitiveTypeProvider(): array {
		return array(
			'valid string'                    => array( array( 'type' => 'string' ), 'hello', true ),
			'invalid string (integer given)'  => array(
				array( 'type' => 'string' ),
				123,
				false,
				'Expected type "string" but got type "integer".',
				'type-mismatch',
				'#/',
			),
			'valid integer'                   => array( array( 'type' => 'integer' ), 42, true ),
			'invalid integer (string given)'  => array(
				array( 'type' => 'integer' ),
				'foo',
				false,
				'Expected type "integer" but got type "string".',
				'type-mismatch',
				'#/',
			),
			'valid boolean true'              => array( array( 'type' => 'boolean' ), true, true ),
			'valid boolean false'             => array( array( 'type' => 'boolean' ), false, true ),
			'invalid boolean (integer given)' => array(
				array( 'type' => 'boolean' ),
				0,
				false,
				'Expected type "boolean" but got type "integer".',
				'type-mismatch',
				'#/',
			),
			'valid number (float)'            => array( array( 'type' => 'number' ), 3.14, true ),
			'valid number (integer)'          => array( array( 'type' => 'number' ), 7, true ),
			'invalid number (string given)'   => array(
				array( 'type' => 'number' ),
				'7.0',
				false,
				'Expected type "number" but got type "string".',
				'type-mismatch',
				'#/',
			),
		);
	}

	/**
	 * @dataProvider primitiveTypeProvider
	 */
	public function testPrimitiveTypeValidation(
		array $schema,
		$value,
		bool $shouldBeValid
	) {
		$validator = new HumanFriendlySchemaValidator( $schema );
		$error     = $validator->validate( $value );
		$this->assertIsValid( $error, $shouldBeValid );
	}

	// Test Enums
	public static function enumProvider(): array {
		return array(
			'valid enum string'              => array(
				array(
					'type' => 'string',
					'enum' => array( 'a', 'b' ),
				),
				'a',
				true,
			),
			'invalid enum string'            => array(
				array(
					'type' => 'string',
					'enum' => array( 'a', 'b' ),
				),
				'c',
				false,
				'The provided value ("c") is not allowed here. Please use one of the following: a, b.',
				'enum-mismatch',
				'#/',
			),
			'valid enum integer'             => array(
				array(
					'type' => 'integer',
					'enum' => array( 1, 2 ),
				),
				2,
				true,
			),
			'invalid enum integer'           => array(
				array(
					'type' => 'integer',
					'enum' => array( 1, 2 ),
				),
				3,
				false,
				'The provided value (3) is not allowed here. Please use one of the following: 1, 2.',
				'enum-mismatch',
				'#/',
			),
			'enum with empty string allowed' => array(
				array(
					'type' => 'string',
					'enum' => array( '', 'foo' ),
				),
				'',
				true,
			),
		);
	}

	/**
	 * @dataProvider enumProvider
	 */
	public function testEnumValidation(
		array $schema,
		$value,
		bool $shouldBeValid
	) {
		$validator = new HumanFriendlySchemaValidator( $schema );
		$error     = $validator->validate( $value );
		$this->assertIsValid( $error, $shouldBeValid );
	}

	// Test Objects
	public static function objectProvider(): array {
		$baseSchema = array(
			'type'       => 'object',
			'properties' => array(
				'foo' => array( 'type' => 'string' ),
				'bar' => array( 'type' => 'integer' ),
			),
		);

		return array(
			'valid object'                                                         => array(
				array_merge( $baseSchema, array( 'required' => array( 'foo' ) ) ),
				array(
					'foo' => 'text',
					'bar' => 123,
				),
				true,
			),
			'valid object with optional property missing'                          => array(
				array_merge( $baseSchema, array( 'required' => array( 'foo' ) ) ),
				array( 'foo' => 'text' ),
				true,
			),
			'invalid object missing required property'                             => array(
				array_merge( $baseSchema, array( 'required' => array( 'foo', 'bar' ) ) ),
				array( 'foo' => 'text' ),
				false,
				'Missing required field: bar.',
			),
			'invalid object missing multiple required properties' => array(
				array_merge( $baseSchema, array( 'required' => array( 'foo', 'bar' ) ) ),
				array(),
				false,
				'Object validation failed.',
				array( 'Missing required field: foo.', 'Missing required field: bar.' ),
			),
			'invalid object property type'                                         => array(
				$baseSchema,
				array( 'foo' => 123 ), // foo should be string
				false,
				'Expected type "string" but got type "integer".',
			),
			'object with additionalProperties: false, extra prop' => array(
				array_merge( $baseSchema, array( 'additionalProperties' => false ) ),
				array(
					'foo' => 'text',
					'extra' => 'disallowed',
				),
				false,
				'Property "extra" isn\'t allowed here. Allowed properties are: foo, bar.',
			),
			'object with additionalProperties: true, extra prop' => array( // True is default, but explicit for test
				array_merge( $baseSchema, array( 'additionalProperties' => true ) ),
				array(
					'foo' => 'text',
					'extra' => 'allowed',
				),
				true,
			),
			'object with additionalProperties: schema, valid extra prop' => array(
				array_merge( $baseSchema, array( 'additionalProperties' => array( 'type' => 'boolean' ) ) ),
				array(
					'foo' => 'text',
					'extra' => true,
				),
				true,
			),
			'object with additionalProperties: schema, invalid extraextra prop' => array(
				array_merge( $baseSchema, array( 'additionalProperties' => array( 'type' => 'boolean' ) ) ),
				array(
					'foo' => 'text',
					'extra' => 'not a bool',
				),
				false,
				'Expected type "boolean" but got type "string".',
			),
			'object with no properties defined, only additionalProperties: schema' => array(
				array(
					'type' => 'object',
					'additionalProperties' => array( 'type' => 'string' ),
				),
				array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
				true,
			),
			'object with only required, no properties'                             => array(
				array(
					'type' => 'object',
					'required' => array( 'mustExist' ),
				),
				array( 'mustExist' => 'here' ),
				true,
			),
			'object with only required, no properties, missing required' => array(
				array(
					'type' => 'object',
					'required' => array( 'mustExist' ),
				),
				array( 'otherKey' => 'not it' ),
				false,
				'Missing required field: mustExist.',
			),
			'invalid object with multiple violations'                              => array(
				array_merge(
					$baseSchema,
					array(
						'required' => array( 'foo', 'bar' ),
						'additionalProperties' => false,
					)
				),
				array(
					'foo' => 123,
					'extra' => 'disallowed',
				),
				false,
				'Object validation failed.',
				array(
					'Expected type "string" but got type "integer".',
					'Missing required field: bar.',
					'Property "extra" isn\'t allowed here. Allowed properties are: foo, bar.',
				),
			),
		);
	}

	/**
	 * @dataProvider objectProvider
	 */
	public function testObjectValidation(
		array $schema,
		$value,
		bool $shouldBeValid,
		?string $expectedErrorMessage = null,
		array $expectedChildErrorChecks = array(),
		?string $expectedParentCode = null
	) {
		$validator = new HumanFriendlySchemaValidator( $schema );
		$error     = $validator->validate( $value );

		if ( $shouldBeValid ) {
			$this->assertIsValid( $error, $shouldBeValid );
		} else {
			$this->assertInstanceOf( ValidationError::class, $error, 'Parent error should be a ValidationError instance.' );
			if ( $expectedParentCode !== null ) {
				$this->assertEquals( $expectedParentCode, $error->code, 'Parent error code mismatch.' );
			}

			if ( null !== $expectedErrorMessage ) {
				$this->assertEquals( $expectedErrorMessage, $error->message );
			}

			if ( ! empty( $expectedChildErrorChecks ) ) {
				$this->assertCount( count( $expectedChildErrorChecks ), $error->children, 'Children count mismatch.' );
				foreach ( $expectedChildErrorChecks as $index => $check ) {
					$childError = $error->children[ $index ] ?? null;
					$this->assertInstanceOf( ValidationError::class, $childError, 'Expected a ValidationError instance.' );
					if ( isset( $check->messageContains ) ) {
						$this->assertStringContainsString( $check->messageContains, $childError->message, 'Error message mismatch.' );
					}
					if ( isset( $check->code ) ) {
						$this->assertEquals( $check->code, $childError->code, 'Error code mismatch.' );
					}
					if ( isset( $check->pointer ) ) {
						$this->assertEquals( $check->pointer, $childError->pointer, 'Error pointer mismatch.' );
					}
				}
			}
		}
	}

	// Test Arrays
	public static function arrayProvider(): array {
		return array(
			'valid array of strings'                           => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
				array( 'a', 'b', 'c' ),
				true,
			),
			'invalid array item type'                          => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
				array( 'a', 123, 'c' ),
				false,
				'Expected type "string" but got type "integer".',
			),
			'array with minItems: valid'                       => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'integer' ),
					'minItems' => 2,
				),
				array( 1, 2 ),
				true,
			),
			'array with minItems: invalid'                     => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'integer' ),
					'minItems' => 2,
				),
				array( 1 ),
				false,
				'Need at least 2 items, found 1.',
			),
			'array with maxItems: valid'                       => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'integer' ),
					'maxItems' => 2,
				),
				array( 1, 2 ),
				true,
			),
			'array with maxItems: invalid'                     => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'integer' ),
					'maxItems' => 1,
				),
				array( 1, 2 ),
				false,
				'May contain at most 1 items, found 2.',
			),
			'empty array, items schema defined: valid'         => array(
				array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
				array(),
				true,
			),
			'array with complex items (objects): valid'        => array(
				array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array( 'id' => array( 'type' => 'integer' ) ),
						'required'   => array( 'id' ),
					),
				),
				array( array( 'id' => 1 ), array( 'id' => 2 ) ),
				true,
			),
			'array with complex items (objects): invalid item' => array(
				array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array( 'id' => array( 'type' => 'integer' ) ),
						'required'   => array( 'id' ),
					),
				),
				array( array( 'id' => 1 ), array( 'name' => 'oops' ) ), // second item missing 'id'
				false,
				'Missing required field: id.',
			),
		);
	}

	/**
	 * @dataProvider arrayProvider
	 */
	public function testArrayValidation( array $schema, $value, bool $shouldBeValid, ?string $expectedErrorMessage = null ) {
		$validator = new HumanFriendlySchemaValidator( $schema );
		$result    = $validator->validate( $value );
		$this->assertIsValid( $result, $shouldBeValid, $expectedErrorMessage );
		if ( ! $shouldBeValid && $expectedErrorMessage ) {
			$this->assertEquals( $expectedErrorMessage, $result->message );
		}
	}

	// Test anyOf
	public static function anyOfProvider(): array {
		return array(
			'anyOf: matches first schema (string)'                                     => array(
				array( 'anyOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
				'i am a string',
				true,
			),
			'anyOf: matches second schema (integer)'                                   => array(
				array( 'anyOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
				123,
				true,
			),
			'anyOf: matches no schema (boolean given)'                                 => array(
				array( 'anyOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
				true,
				false,
				'Value must be one of the following types: [string, integer], but it was of type "boolean".',
			),
			'anyOf: overlapping schemas, matches both (number and integer for an int)' => array(
				array( 'anyOf' => array( array( 'type' => 'number' ), array( 'type' => 'integer' ) ) ),
				5, // Matches both 'number' and 'integer'
				true, // anyOf should pass if at least one matches
			),
			// Partial matches with object schemas
			'anyOf: partial match with object schemas'                                 => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
								'b' => array( 'type' => 'integer' ),
							),
							'required'   => array( 'a', 'b' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'c' => array( 'type' => 'string' ),
								'd' => array( 'type' => 'integer' ),
							),
							'required'   => array( 'c', 'd' ),
						),
					),
				),
				array(
					'a' => 'value',
					'b' => 123,
				), // Matches first schema
				true,
			),
			'anyOf: another partial match with object schemas' => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
								'b' => array( 'type' => 'integer' ),
							),
							'required'   => array( 'a', 'b' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'c' => array( 'type' => 'string' ),
								'd' => array( 'type' => 'integer' ),
							),
							'required'   => array( 'c', 'd' ),
						),
					),
				),
				array(
					'c' => 'value',
					'd' => 456,
				), // Matches second schema
				true,
			),
			'anyOf: no match with useful error message'                                => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
								'b' => array( 'type' => 'integer' ),
							),
							'required'   => array( 'a', 'b' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'c' => array( 'type' => 'string' ),
								'd' => array( 'type' => 'integer' ),
							),
							'required'   => array( 'c', 'd' ),
						),
					),
				),
				array(
					'a' => 'value',
					'c' => 'value',
				), // Missing required properties
				false,
				'Value did not match any of the allowed shapes: object.',
				'Missing required fields: b, d.',
			),
			// Near misses with useful error messages
			'anyOf: near miss with wrong type'                                         => array(
				array(
					'anyOf' => array(
						array(
							'type' => 'object',
							'properties' => array( 'a' => array( 'type' => 'string' ) ),
							'required' => array( 'a' ),
						),
						array(
							'type' => 'object',
							'properties' => array( 'b' => array( 'type' => 'string' ) ),
							'required' => array( 'b' ),
						),
					),
				),
				array( 'a' => 123 ), // a should be string but is integer
				false,
				'Value did not match any of the allowed shapes: object.',
				'Expected type "string" but got type "integer".',
			),
			// Nested anyOf (one level deeper)
			'anyOf: one level nested'                                                  => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'nested' => array(
							'anyOf' => array(
								array( 'type' => 'string' ),
								array( 'type' => 'integer' ),
							),
						),
					),
				),
				array( 'nested' => 'string value' ), // Valid nested string
				true,
			),
			'anyOf: one level nested failure'                                          => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'nested' => array(
							'anyOf' => array(
								array( 'type' => 'string' ),
								array( 'type' => 'integer' ),
							),
						),
					),
				),
				array( 'nested' => true ), // Invalid nested value (boolean)
				false,
				'Value must be one of the following types: [string, integer], but it was of type "boolean".',
				null,
			),
			// Two levels deeper nesting
			'anyOf: two levels nested'                                                 => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'level1' => array(
							'type'       => 'object',
							'properties' => array(
								'level2' => array(
									'anyOf' => array(
										array( 'type' => 'string' ),
										array( 'type' => 'integer' ),
									),
								),
							),
						),
					),
				),
				array( 'level1' => array( 'level2' => 42 ) ), // Valid nested integer
				true,
			),
			'anyOf: two levels nested failure'                                         => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'level1' => array(
							'type'       => 'object',
							'properties' => array(
								'level2' => array(
									'anyOf' => array(
										array( 'type' => 'string' ),
										array( 'type' => 'integer' ),
									),
								),
							),
						),
					),
				),
				array( 'level1' => array( 'level2' => false ) ), // Invalid nested value (boolean)
				false,
				'Value must be one of the following types: [string, integer], but it was of type "boolean".',
				null,
			),
			// Mixed types in anyOf
			'anyOf: mixed types - string input'                                        => array(
				array(
					'anyOf' => array(
						array( 'type' => 'string' ),
						array(
							'type' => 'object',
							'properties' => array( 'a' => array( 'type' => 'string' ) ),
						),
						array(
							'type' => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				'valid string', // Valid string input
				true,
			),
			'anyOf: mixed types - object input'                                        => array(
				array(
					'anyOf' => array(
						array( 'type' => 'string' ),
						array(
							'type' => 'object',
							'properties' => array( 'a' => array( 'type' => 'string' ) ),
						),
						array(
							'type' => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				array( 'a' => 'valid object' ), // Valid object input
				true,
			),
			'anyOf: mixed types - array input'                                         => array(
				array(
					'anyOf' => array(
						array( 'type' => 'string' ),
						array(
							'type' => 'object',
							'properties' => array( 'a' => array( 'type' => 'string' ) ),
						),
						array(
							'type' => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				array( 1, 2, 3 ), // Valid array input
				true,
			),
			'anyOf: mixed types - invalid input'                                       => array(
				array(
					'anyOf' => array(
						array( 'type' => 'string' ),
						array(
							'type' => 'object',
							'properties' => array( 'a' => array( 'type' => 'string' ) ),
						),
						array(
							'type' => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				false, // Boolean doesn't match any schema
				false,
				'Value must be one of the following types: [string, object, array], but it was of type "boolean".',
				null, // No explanation for type mismatch
			),
			// Deep ambiguity resolution test
			'anyOf: ambiguity resolved at second level'                                => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'type'  => array(
											'type' => 'string',
											'enum' => array( 'typeA' ),
										),
										'value' => array( 'type' => 'string' ),
									),
									'required'   => array( 'type', 'value' ),
								),
							),
							'required'   => array( 'data' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'type'  => array(
											'type' => 'string',
											'enum' => array( 'typeB' ),
										),
										'count' => array( 'type' => 'integer' ),
									),
									'required'   => array( 'type', 'count' ),
								),
							),
							'required'   => array( 'data' ),
						),
					),
				),
				array(
					'data' => array(
						'type' => 'typeA',
						'value' => 'test string',
					),
				), // Should match first schema
				true,
			),
			'anyOf: ambiguity resolved at second level without enums' => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'type'  => array( 'type' => 'string' ),
										'value' => array( 'type' => 'string' ),
									),
									'required'   => array( 'type', 'value' ),
								),
							),
							'required'   => array( 'data' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'name'  => array( 'type' => 'string' ),
										'count' => array( 'type' => 'integer' ),
									),
									'required'   => array( 'name', 'count' ),
								),
							),
							'required'   => array( 'data' ),
						),
					),
				),
				array(
					'data' => array(
						'name' => 'test name',
						'count' => 123,
					),
				), // Should match first schema
				true,
			),
			'anyOf: invalid at second level'                                           => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'type'  => array(
											'type' => 'string',
											'enum' => array( 'typeA' ),
										),
										'value' => array( 'type' => 'string' ),
									),
									'required'   => array( 'type', 'value' ),
								),
							),
							'required'   => array( 'data' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'type'  => array(
											'type' => 'string',
											'enum' => array( 'typeB' ),
										),
										'count' => array( 'type' => 'integer' ),
									),
									'required'   => array( 'type', 'count' ),
								),
							),
							'required'   => array( 'data' ),
						),
					),
				),
				array(
					'data' => array(
						'type' => 'typeA',
						'count' => 123,
					),
				), // "typeA" but missing required "value"
				false,
				'Value did not match any of the allowed shapes: object.',
				'Missing required field: value.',
			),
			'anyOf: ambiguity unresolved at second level without enums' => array(
				array(
					'anyOf' => array(
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'type'  => array( 'type' => 'string' ),
										'value' => array( 'type' => 'string' ),
									),
									'required'   => array( 'type', 'value' ),
								),
							),
							'required'   => array( 'data' ),
						),
						array(
							'type'       => 'object',
							'properties' => array(
								'data' => array(
									'type'       => 'object',
									'properties' => array(
										'name'  => array( 'type' => 'string' ),
										'count' => array( 'type' => 'integer' ),
									),
									'required'   => array( 'name', 'count' ),
								),
							),
							'required'   => array( 'data' ),
						),
					),
				),
				array(
					'data' => array(
						'lastName' => 'test name',
						'count' => 123,
					),
				), // Should match neither schema
				false,
				'Value did not match any of the allowed shapes: object.',
				'Missing required field: name.',
			),
		);
	}

	/**
	 * @dataProvider anyOfProvider
	 */
	public function testAnyOfValidation(
		array $schema,
		$value,
		bool $shouldBeValid,
		?string $expectedErrorMessage = null,
		?string $expectedExplanation = null
	) {
		$validator = new HumanFriendlySchemaValidator( $schema );
		$result    = $validator->validate( $value );
		$this->assertIsValid( $result, $shouldBeValid );
		if ( ! $shouldBeValid && $expectedErrorMessage ) {
			$this->assertEquals( $expectedErrorMessage, $result->message );
			if ( $expectedExplanation ) {
				$this->assertEquals( $expectedExplanation, $result->getMostProbableCause()->message );
			}
		}
	}

	// Test oneOf
	public static function oneOfProvider(): array {
		return array(
			'oneOf: matches first schema (string)'                            => array(
				array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
				'i am a string',
				true,
			),
			'oneOf: matches second schema (integer)'                          => array(
				array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
				123,
				true,
			),
			'oneOf: matches no schema (boolean given)'                        => array(
				array( 'oneOf' => array( array( 'type' => 'string' ), array( 'type' => 'integer' ) ) ),
				true,
				false,
				'Value must be one of the following types: [string, integer], but it was of type "boolean".',
			),
			'oneOf: matches multiple schemas (number and integer for an int)' => array(
				array( 'oneOf' => array( array( 'type' => 'number' ), array( 'type' => 'integer' ) ) ),
				5, // Matches both 'number' and 'integer'
				false, // oneOf should fail if more than one matches
				'Data matches more than one allowed shape - you need to make it unambiguous. Matched shapes: number, integer.',
			),
			'oneOf: ambiguous object schemas without discriminator' => array(
				array(
					'oneOf' => array(
						array(
							'type' => 'object',
							'properties' => array( 'a' => array( 'type' => 'string' ) ),
						),
						array(
							'type' => 'object',
							'properties' => array( 'b' => array( 'type' => 'string' ) ),
						),
					),
				),
				array(
					'a' => 'value',
					'b' => 'value',
				),
				false, // Should fail because it matches both schemas
				'Data matches more than one allowed shape - you need to make it unambiguous. Matched shapes: object, object.',
			),
			'oneOf: ambiguous object schemas with overlapping properties' => array(
				array(
					'oneOf' => array(
						array(
							'type' => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
								'c' => array( 'type' => 'integer' ),
							),
						),
						array(
							'type' => 'object',
							'properties' => array(
								'a' => array( 'type' => 'string' ),
								'd' => array( 'type' => 'integer' ),
							),
						),
					),
				),
				array(
					'a' => 'value',
					'c' => 1,
					'd' => 2,
				),
				false, // Should fail because it matches both schemas
				'Data matches more than one allowed shape - you need to make it unambiguous. Matched shapes: object, object.',
			),
			'oneOf: ambiguous object schemas with missing discriminator' => array(
				array(
					'oneOf' => array(
						array(
							'type' => 'object',
							'properties' => array(
								'type' => array( 'enum' => array( 'A' ) ),
								'value' => array( 'type' => 'string' ),
							),
						),
						array(
							'type' => 'object',
							'properties' => array(
								'type' => array( 'enum' => array( 'B' ) ),
								'value' => array( 'type' => 'string' ),
							),
						),
					),
				),
				array( 'value' => 'test' ),
				false, // Should fail because discriminator is missing
				'Data matches more than one allowed shape - you need to make it unambiguous. Matched shapes: object, object.',
			),
		);
	}

	/**
	 * @dataProvider oneOfProvider
	 */
	public function testOneOfValidation( array $schema, $value, bool $shouldBeValid, ?string $expectedErrorMessage = null ) {
		$validator = new HumanFriendlySchemaValidator( $schema );
		$result    = $validator->validate( $value );
		$this->assertIsValid( $result, $shouldBeValid );
		if ( ! $shouldBeValid && $expectedErrorMessage ) {
			$this->assertEquals( $expectedErrorMessage, $result->message );
		}
	}

	// Test $ref (local references only)
	public function testLocalRefValidation() {
		$schema    = array(
			'definitions' => array(
				'name' => array( 'type' => 'string' ),
				'user' => array(
					'type'       => 'object',
					'properties' => array(
						'username' => array( '$ref' => '#/definitions/name' ),
						'id'       => array( 'type' => 'integer' ),
					),
					'required'   => array( 'username', 'id' ),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'admin' => array( '$ref' => '#/definitions/user' ),
			),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid
		$this->assertIsValid(
			$validator->validate(
				array(
					'admin' => array(
						'username' => 'test',
						'id' => 1,
					),
				)
			)
		);

		// Invalid: property type within referenced schema
		$resultInvalidType = $validator->validate(
			array(
				'admin' => array(
					'username' => 'test',
					'id' => 'not-an-int',
				),
			)
		);
		$this->assertInstanceOf( ValidationError::class, $resultInvalidType );
		$this->assertEquals( 'Expected type "integer" but got type "string".', $resultInvalidType->message );
		$this->assertEquals( '#/admin/id', $resultInvalidType->pointer );
	}

	/**
	 * Test anyOf with references
	 */
	public function testAnyOfWithReferences() {
		$schema    = array(
			'definitions' => array(
				'stringConfig' => array( 'type' => 'string' ),
				'numberConfig' => array( 'type' => 'integer' ),
				'objectConfig' => array(
					'type'       => 'object',
					'properties' => array(
						'name'  => array( 'type' => 'string' ),
						'value' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'name', 'value' ),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'config' => array(
					'anyOf' => array(
						array( '$ref' => '#/definitions/stringConfig' ),
						array( '$ref' => '#/definitions/numberConfig' ),
						array( '$ref' => '#/definitions/objectConfig' ),
					),
				),
			),
			'required'    => array( 'config' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid string reference
		$this->assertIsValid( $validator->validate( array( 'config' => 'string value' ) ) );

		// Valid number reference
		$this->assertIsValid( $validator->validate( array( 'config' => 42 ) ) );

		// Valid object reference
		$this->assertIsValid(
			$validator->validate(
				array(
					'config' => array(
						'name' => 'test',
						'value' => 123,
					),
				)
			)
		);

		// Invalid: doesn't match any reference schema
		$result1 = $validator->validate( array( 'config' => true ) );
		$this->assertInstanceOf( ValidationError::class, $result1 );
		$this->assertEquals(
			'Value must be one of the following types: [string, integer, object], but it was of type "boolean".',
			$result1->message
		);

		// Invalid: partial match with object reference
		$result2 = $validator->validate( array( 'config' => array( 'name' => 'test' ) ) );
		$this->assertInstanceOf( ValidationError::class, $result2 );
		$this->assertStringContainsString( 'Missing required field: value', $result2->message );
	}

	/**
	 * Test oneOf with references
	 */
	public function testOneOfWithReferences() {
		$schema    = array(
			'definitions' => array(
				'categoryA' => array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'A' ),
						),
						'value' => array( 'type' => 'string' ),
					),
					'required'   => array( 'type', 'value' ),
				),
				'categoryB' => array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'B' ),
						),
						'count' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'type', 'count' ),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'data' => array(
					'oneOf' => array(
						array( '$ref' => '#/definitions/categoryA' ),
						array( '$ref' => '#/definitions/categoryB' ),
					),
				),
			),
			'required'    => array( 'data' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid category A
		$this->assertIsValid(
			$validator->validate(
				array(
					'data' => array(
						'type' => 'A',
						'value' => 'test',
					),
				)
			)
		);

		// Valid category B
		$this->assertIsValid(
			$validator->validate(
				array(
					'data' => array(
						'type' => 'B',
						'count' => 42,
					),
				)
			)
		);

		// Invalid: matches neither
		$result1 = $validator->validate(
			array(
				'data' => array(
					'type' => 'C',
					'value' => 'test',
				),
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result1 );
		$this->assertEquals( 'Property "type" must be one of [A, B], but it was "C".', $result1->message );

		// Invalid: missing required property in the matched reference
		$result2 = $validator->validate(
			array(
				'data' => array(
					'type' => 'A',
					'count' => 42,
				),
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result2 );
		$this->assertStringContainsString( 'Missing required field: value', $result2->message );
	}

	/**
	 * Test for mixed references and inline schemas
	 */
	public function testMixedReferencesAndInlineSchemas() {
		$schema    = array(
			'definitions' => array(
				'stringProperty'  => array( 'type' => 'string' ),
				'integerProperty' => array( 'type' => 'integer' ),
			),
			'type'        => 'object',
			'properties'  => array(
				'mixed' => array(
					'anyOf' => array(
						array( '$ref' => '#/definitions/stringProperty' ),
						array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array( '$ref' => '#/definitions/stringProperty' ),
								'count' => array( '$ref' => '#/definitions/integerProperty' ),
							),
							'required'   => array( 'name', 'count' ),
						),
					),
				),
			),
			'required'    => array( 'mixed' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid string reference
		$this->assertIsValid( $validator->validate( array( 'mixed' => 'string value' ) ) );

		// Valid inline object with referenced properties
		$this->assertIsValid(
			$validator->validate(
				array(
					'mixed' => array(
						'name' => 'test',
						'count' => 42,
					),
				)
			)
		);

		// Invalid: object with wrong property types
		$result = $validator->validate(
			array(
				'mixed' => array(
					'name' => 123,
					'count' => 'not a number',
				),
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result );
		$this->assertEquals( 'Object validation failed.', $result->message );
		$this->assertEquals( 'Expected type "string" but got type "integer".', $result->getMostProbableCause()->message );
	}

	/**
	 * Test nested structure with references
	 */
	public function testNestedStructureWithReferences() {
		$schema    = array(
			'definitions' => array(
				'idObject'   => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'nameObject' => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array( 'type' => 'string' ),
					),
					'required'   => array( 'name' ),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'nested' => array(
					'type'       => 'object',
					'properties' => array(
						'inner' => array(
							'anyOf' => array(
								array( '$ref' => '#/definitions/idObject' ),
								array( '$ref' => '#/definitions/nameObject' ),
							),
						),
					),
					'required'   => array( 'inner' ),
				),
			),
			'required'    => array( 'nested' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid with id reference
		$this->assertIsValid( $validator->validate( array( 'nested' => array( 'inner' => array( 'id' => 'test-id' ) ) ) ) );

		// Valid with name reference
		$this->assertIsValid( $validator->validate( array( 'nested' => array( 'inner' => array( 'name' => 'test-name' ) ) ) ) );

		// Invalid: neither reference matches
		$result = $validator->validate( array( 'nested' => array( 'inner' => array( 'description' => 'wrong property' ) ) ) );
		$this->assertInstanceOf( ValidationError::class, $result );
		$this->assertStringContainsString( 'Value did not match any of the allowed shapes', $result->message );
		$this->assertStringContainsString( 'Missing required field: id.', $result->children[0]->message );
	}

	/**
	 * Test circular references (which are not supported)
	 */
	public function testCircularReferences() {
		$schema = array(
			'definitions' => array(
				'recursive' => array(
					'type'       => 'object',
					'properties' => array(
						'child' => array( '$ref' => '#/definitions/recursive' ),
					),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'data' => array( '$ref' => '#/definitions/recursive' ),
			),
		);

		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->assertIsValid( $validator->validate( array( 'data' => array( 'child' => array() ) ) ) );
	}

	public function testUnsupportedExternalRefThrows() {
		$schema    = array(
			'type' => 'object',
			'properties' => array( 'foo' => array( '$ref' => 'external.json#/foo' ) ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'Only local #/ refs are supported' );
		$validator->validate( array( 'foo' => 'bar' ) );
	}

	public function testInvalidLocalRefPathThrows() {
		$schema    = array(
			'type' => 'object',
			'properties' => array( 'foo' => array( '$ref' => '#/definitions/nonExistent' ) ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'Reference #/definitions/nonExistent not found' );
		$validator->validate( array( 'foo' => 'bar' ) );
	}

	// Test Schema Issues
	public function testUnknownSchemaNode() {
		$schema = array(
			'type' => 'object',
			'properties' => array( 'foo' => array( 'weirdKeyword' => true ) ),
		);
		$this->expectException( UnsupportedSchemaException::class );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$validator->validate( array( 'foo' => 'bar' ) );
	}

	public function testEmptySchema() {
		$schema = array(); // No type, no anyOf/oneOf
		$this->expectException( UnsupportedSchemaException::class );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$validator->validate( 'anything' );
	}

	public function testUnknownTypeInSchema() {
		$schema = array( 'type' => 'futureType' );
		$this->expectException( UnsupportedSchemaException::class );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$result    = $validator->validate( 'data' );
	}

	// Test Input Variations
	public function testNullInput() {
		$schema    = array( 'type' => 'string' );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$result    = $validator->validate( null );
		$this->assertInstanceOf( ValidationError::class, $result );
		$this->assertEquals( 'Expected type "string" but got type "NULL".', $result->message ); // PHP gettype(null) is "NULL"
	}

	public function testUnexpectedInputTypeResource() {
		$schema    = array( 'type' => 'string' );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$resource  = fopen( 'php://memory', 'r' );
		$result    = $validator->validate( $resource );
		fclose( $resource );
		$this->assertInstanceOf( ValidationError::class, $result );
		$this->assertStringContainsString( 'Expected type "string" but got type "resource".', $result->message );
	}

	public function testValidatorDoesNotMutateInput() {
		$schema    = array(
			'type' => 'object',
			'properties' => array( 'foo' => array( 'type' => 'string' ) ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$input     = array( 'foo' => 'bar' );
		$inputCopy = $input;
		$validator->validate( $input ); // Call validation
		$this->assertSame( $inputCopy, $input, 'Input data should not be mutated.' );
	}

	// Test Edge Cases
	public function testDeeplyNestedObjects() {
		$schema    = array(
			'type'       => 'object',
			'properties' => array(
				'a' => array(
					'type'       => 'object',
					'properties' => array(
						'b' => array(
							'type'       => 'object',
							'properties' => array(
								'c' => array(
									'type' => 'string',
								),
							),
							'required'   => array( 'c' ),
						),
					),
					'required'   => array( 'b' ),
				),
			),
			'required'   => array( 'a' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		// Valid
		$this->assertIsValid( $validator->validate( array( 'a' => array( 'b' => array( 'c' => 'ok' ) ) ) ) );
		// Invalid
		$result = $validator->validate( array( 'a' => array( 'b' => array( 'd' => 'wrong' ) ) ) ); // c is missing
		$this->assertInstanceOf( ValidationError::class, $result );
		$this->assertEquals( 'Missing required field: c.', $result->message );
		$this->assertEquals( '#/a/b', $result->pointer );
	}

	public function testLargeArrayPerformanceStub() {
		// This is not a true performance test but checks for crashes with large arrays.
		$schema     = array(
			'type' => 'array',
			'items' => array( 'type' => 'integer' ),
		);
		$validator  = new HumanFriendlySchemaValidator( $schema );
		$largeArray = range( 1, 500 ); // Reduced from 10000 to avoid excessive test time / memory
		$result     = $validator->validate( $largeArray );
		$this->assertIsValid( $result );
		// Test invalid large array
		$largeArray[]  = 'not_an_integer';
		$resultInvalid = $validator->validate( $largeArray );
		$this->assertInstanceOf( ValidationError::class, $resultInvalid );
		$this->assertStringContainsString( 'Expected type "integer" but got type "string".', $resultInvalid->message );
	}

	public function testDiscriminatorLikeAnyOf() {
		$schema    = array(
			'anyOf' => array(
				array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'A' ),
						),
						'propA' => array( 'type' => 'string' ),
					),
					'required'   => array( 'type', 'propA' ),
				),
				array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'B' ),
						),
						'propB' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'type', 'propB' ),
				),
			),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid type A
		$this->assertIsValid(
			$validator->validate(
				array(
					'type' => 'A',
					'propA' => 'hello',
				)
			)
		);
		// Valid type B
		$this->assertIsValid(
			$validator->validate(
				array(
					'type' => 'B',
					'propB' => 123,
				)
			)
		);

		// Invalid: type A data with type B value (missing propA for matched 'A' schema)
		$result1 = $validator->validate(
			array(
				'type' => 'A',
				'propB' => 123,
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result1 );
		$this->assertStringContainsString( 'Missing required field: propA', $result1->message );

		// Invalid: type B data with type A value (missing propB for matched 'B' schema)
		$result2 = $validator->validate(
			array(
				'type' => 'B',
				'propA' => 'hello',
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result2 );
		$this->assertStringContainsString( 'Missing required field: propB', $result2->message );

		// Invalid: unknown type value for discriminator
		$result3 = $validator->validate(
			array(
				'type' => 'C',
				'propA' => 'hello',
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result3 );
		$this->assertEquals( 'Property "type" must be one of [A, B], but it was "C".', $result3->message );

		// Invalid: missing type (discriminator property)
		$result4 = $validator->validate( array( 'propA' => 'hello' ) );
		$this->assertInstanceOf( ValidationError::class, $result4 );
		$this->assertEquals( 'Property "type" must be one of [A, B], but it was missing.', $result4->message );
	}

	public function testArrayIsValidObjectOption() {
		$schema = array(
			'type' => 'object',
			'properties' => array( 'a' => array( 'type' => 'string' ) ),
		);

		// Default: array is valid object
		$validatorDefault = new HumanFriendlySchemaValidator( $schema ); // array_is_valid_object defaults to true
		$resultDefault    = $validatorDefault->validate( array( 'a' => 'test' ) ); // Using PHP array for object
		$this->assertIsValid( $resultDefault );

		// Option false: array is NOT valid object
		$validatorStrict = new HumanFriendlySchemaValidator( $schema, array( 'array_is_valid_object' => false ) );
		$resultStrict    = $validatorStrict->validate( array( 'a' => 'test' ) ); // Using PHP array for object
		$this->assertInstanceOf( ValidationError::class, $resultStrict );
		$this->assertEquals( 'Expected type "object" but got type "array".', $resultStrict->message );

		// Still validates actual objects correctly
		$stdClass     = new stdClass();
		$stdClass->a  = 'test';
		$resultObject = $validatorStrict->validate( $stdClass );
		$this->assertIsValid( $resultObject );
	}

	public function testNotThrows() {
		$schema    = array( 'not' => array( 'type' => 'string' ) );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'The schema keyword "not" is not supported' );
		$validator->validate( 'test' );
	}

	public function testPatternThrows() {
		$schema    = array(
			'type' => 'string',
			'pattern' => '^[a-z]+$',
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'The string constraint "pattern" is not supported' );
		$validator->validate( 'test' );
	}

	public function testMinimumThrows() {
		$schema    = array(
			'type' => 'number',
			'minimum' => 5,
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'The numeric constraint "minimum" is not supported' );
		$validator->validate( 10 );
	}

	public function testMaximumThrows() {
		$schema    = array(
			'type' => 'integer',
			'maximum' => 100,
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'The numeric constraint "maximum" is not supported' );
		$validator->validate( 50 );
	}

	public function testUniqueItemsThrows() {
		$schema    = array(
			'type' => 'array',
			'items' => array( 'type' => 'string' ),
			'uniqueItems' => true,
		);
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'The array constraint "uniqueItems" is not supported' );
		$validator->validate( array( 'a', 'b', 'c' ) );
	}

	public function testTypeAsArray() {
		$schema    = array( 'type' => array( 'string', 'integer' ) );
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->assertIsValid( $validator->validate( 'test' ) );
		$this->assertIsValid( $validator->validate( 123 ) );
		$this->assertIsInvalid( $validator->validate( array() ) );
	}

	public function testEnumMismatchedTypeThrows() {
		$schema    = array(
			'type' => 'string',
			'enum' => array( 'valid', 123 ),
		); // 123 is not a string
		$validator = new HumanFriendlySchemaValidator( $schema );
		$this->expectException( UnsupportedSchemaException::class );
		$this->expectExceptionMessage( 'Enum value 123 does not match the declared type "string"' );
		$validator->validate( 'valid' );
	}

	/**
	 * Test anyOf with mixed types including references
	 */
	public function testAnyOfWithMixedTypesAndReferences() {
		$schema    = array(
			'definitions' => array(
				'stringDef' => array( 'type' => 'string' ),
				'numberDef' => array( 'type' => 'number' ),
				'objectDef' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
			),
			'anyOf'       => array(
				array( '$ref' => '#/definitions/stringDef' ),
				array( '$ref' => '#/definitions/numberDef' ),
				array( '$ref' => '#/definitions/objectDef' ),
				array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Test valid string reference
		$this->assertIsValid( $validator->validate( 'test string' ) );

		// Test valid number reference
		$this->assertIsValid( $validator->validate( 42.5 ) );

		// Test valid object reference
		$this->assertIsValid( $validator->validate( array( 'id' => 'test-id' ) ) );

		// Test valid array (inline schema)
		$this->assertIsValid( $validator->validate( array( 'a', 'b', 'c' ) ) );

		// Test invalid type (boolean)
		$result1 = $validator->validate( true );
		$this->assertInstanceOf( ValidationError::class, $result1 );
		$this->assertEquals(
			'Value must be one of the following types: [string, number, object, array], but it was of type "boolean".',
			$result1->message
		);
	}

	/**
	 * Test anyOf with discriminated references
	 */
	public function testAnyOfWithDiscriminatedReferences() {
		$schema    = array(
			'definitions' => array(
				'typeA' => array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'A' ),
						),
						'value' => array( 'type' => 'string' ),
					),
					'required'   => array( 'type', 'value' ),
				),
				'typeB' => array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'B' ),
						),
						'count' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'type', 'count' ),
				),
			),
			'anyOf'       => array(
				array( '$ref' => '#/definitions/typeA' ),
				array( '$ref' => '#/definitions/typeB' ),
			),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid type A
		$this->assertIsValid(
			$validator->validate(
				array(
					'type' => 'A',
					'value' => 'test',
				)
			)
		);

		// Valid type B
		$this->assertIsValid(
			$validator->validate(
				array(
					'type' => 'B',
					'count' => 42,
				)
			)
		);

		// Invalid discriminator value
		$result1 = $validator->validate(
			array(
				'type' => 'C',
				'value' => 'test',
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result1 );
		// Check for direct enum error message
		$this->assertEquals( 'Property "type" must be one of [A, B], but it was "C".', $result1->message );

		// Invalid missing discriminator
		$result2 = $validator->validate( array( 'value' => 'test' ) );
		$this->assertEquals( 'Property "type" must be one of [A, B], but it was missing.', $result2->message );
	}

	/**
	 * Test anyOf with explicit discriminator
	 */
	public function testAnyOfWithExplicitDiscriminator() {
		$schema    = array(
			'definitions' => array(
				'dogType' => array(
					'type'       => 'object',
					'properties' => array(
						'type'  => array(
							'type' => 'string',
							'enum' => array( 'dog' ),
						),
						'breed' => array( 'type' => 'string' ),
						'age'   => array( 'type' => 'integer' ),
					),
					'required'   => array( 'type', 'breed' ),
				),
				'catType' => array(
					'type'       => 'object',
					'properties' => array(
						'type'   => array(
							'type' => 'string',
							'enum' => array( 'cat' ),
						),
						'color'  => array( 'type' => 'string' ),
						'indoor' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'type', 'color' ),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'pet' => array(
					'anyOf'         => array(
						array( '$ref' => '#/definitions/dogType' ),
						array( '$ref' => '#/definitions/catType' ),
					),
					'discriminator' => array(
						'propertyName' => 'type',
					),
				),
			),
			'required'    => array( 'pet' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid dog reference
		$this->assertIsValid(
			$validator->validate(
				array(
					'pet' => array(
						'type' => 'dog',
						'breed' => 'Labrador',
						'age' => 3,
					),
				)
			)
		);

		// Valid cat reference
		$this->assertIsValid(
			$validator->validate(
				array(
					'pet' => array(
						'type' => 'cat',
						'color' => 'black',
						'indoor' => true,
					),
				)
			)
		);

		// Invalid: wrong discriminator value
		$result1 = $validator->validate(
			array(
				'pet' => array(
					'type' => 'bird',
					'species' => 'parrot',
				),
			)
		);
		$this->assertIsValid( $result1, false );

		// Just check that we have an error, without specifying its exact content
		$this->assertEquals( 'Property "type" must be one of [dog, cat], but it was "bird".', $result1->message );

		// Invalid: missing discriminator property
		$result2 = $validator->validate(
			array(
				'pet' => array(
					'breed' => 'Labrador',
					'age' => 3,
				),
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result2 );
		// Check for message about missing type field
		$this->assertEquals( 'Property "type" must be one of [dog, cat], but it was missing.', $result2->message );

		// Invalid: correct discriminator but missing required property
		$result3 = $validator->validate(
			array(
				'pet' => array(
					'type' => 'dog',
					'age' => 3,
				),
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result3 );
		$this->assertEquals( 'Missing required field: breed.', $result3->message );
	}

	/**
	 * Test anyOf with implicit discriminator (inferred from enum values)
	 */
	public function testAnyOfWithImplicitDiscriminator() {
		$schema    = array(
			'definitions' => array(
				'configA' => array(
					'type'       => 'object',
					'properties' => array(
						'mode'  => array(
							'type' => 'string',
							'enum' => array( 'A' ),
						),
						'value' => array( 'type' => 'string' ),
					),
					'required'   => array( 'mode', 'value' ),
				),
				'configB' => array(
					'type'       => 'object',
					'properties' => array(
						'mode'  => array(
							'type' => 'string',
							'enum' => array( 'B' ),
						),
						'count' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'mode', 'count' ),
				),
			),
			'anyOf'       => array(
				array( '$ref' => '#/definitions/configA' ),
				array( '$ref' => '#/definitions/configB' ),
				array( 'type' => 'string' ),
			),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid config A
		$this->assertIsValid(
			$validator->validate(
				array(
					'mode' => 'A',
					'value' => 'test',
				)
			)
		);

		// Valid config B
		$this->assertIsValid(
			$validator->validate(
				array(
					'mode' => 'B',
					'count' => 123,
				)
			)
		);

		// Valid string
		$this->assertIsValid( $validator->validate( 'simple string' ) );

		// Invalid: wrong discriminator value
		$result1 = $validator->validate(
			array(
				'mode' => 'C',
				'value' => 'test',
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result1 );
		// Check for error about wrong enum value
		$this->assertStringContainsString( 'Property "mode" must be one of [A, B], but it was "C".', $result1->message );

		// Invalid: missing discriminator property but has other object properties
		$result2 = $validator->validate(
			array(
				'value' => 'test',
				'count' => 123,
			)
		);
		$this->assertInstanceOf( ValidationError::class, $result2 );
		$this->assertEquals( 'Property "mode" must be one of [A, B], but it was missing.', $result2->message );

		// Invalid: wrong type entirely
		$result3 = $validator->validate( 123 );
		$this->assertInstanceOf( ValidationError::class, $result3 );
		$this->assertStringContainsString(
			'Value must be one of the following types: [object, string], but it was of type "integer".',
			$result3->message
		);
	}

	/**
	 * Test anyOf with mixed types (refs, objects, arrays, primitives) and no discriminator
	 */
	public function testAnyOfWithMixedTypesNoDiscriminator() {
		$schema    = array(
			'definitions' => array(
				'stringType'  => array( 'type' => 'string' ),
				'numberType'  => array( 'type' => 'number' ),
				'simpleArray' => array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'anyOf'       => array(
				array( '$ref' => '#/definitions/stringType' ),
				array( '$ref' => '#/definitions/numberType' ),
				array( '$ref' => '#/definitions/simpleArray' ),
				array(
					'type'       => 'object',
					'properties' => array(
						'name'   => array( 'type' => 'string' ),
						'values' => array( '$ref' => '#/definitions/simpleArray' ),
					),
					'required'   => array( 'name' ),
				),
			),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid string
		$this->assertIsValid( $validator->validate( 'test string' ) );

		// Valid number
		$this->assertIsValid( $validator->validate( 42.5 ) );

		// Valid array reference
		$this->assertIsValid( $validator->validate( array( 'one', 'two', 'three' ) ) );

		// Valid object with array reference
		$this->assertIsValid(
			$validator->validate(
				array(
					'name' => 'test object',
					'values' => array( 'a', 'b', 'c' ),
				)
			)
		);

		// Invalid: object missing required property
		$result1 = $validator->validate( array( 'values' => array( 'a', 'b', 'c' ) ) );
		$this->assertInstanceOf( ValidationError::class, $result1 );
		$this->assertEquals( 'Missing required field: name.', $result1->message );

		// Invalid: array with wrong item type
		$result2 = $validator->validate( array( 1, 2, 3 ) );
		$this->assertInstanceOf( ValidationError::class, $result2 );
		$this->assertEquals( 'Array validation failed.', $result2->message );
		$this->assertEquals( 'Expected type "string" but got type "integer".', $result2->getMostProbableCause()->message );

		// Invalid: completely wrong type
		$result3 = $validator->validate( true );
		$this->assertInstanceOf( ValidationError::class, $result3 );
		$this->assertStringContainsString(
			'Value must be one of the following types: [string, number, array, object], but it was of type "boolean".',
			$result3->message
		);
	}

	/**
	 * Test anyOf with multiple inline objects and references combined
	 */
	public function testAnyOfWithComplexCombinations() {
		$schema = array(
			'definitions' => array(
				'idObject' => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'string' ),
						'active' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
			),
			'type'        => 'object',
			'properties'  => array(
				'config' => array(
					'anyOf' => array(
						// Reference
						array( '$ref' => '#/definitions/idObject' ),
						// Inline object
						array(
							'type'       => 'object',
							'properties' => array(
								'name'   => array( 'type' => 'string' ),
								'values' => array(
									'type' => 'array',
									'items' => array( 'type' => 'number' ),
								),
							),
							'required'   => array( 'name' ),
						),
						// Simple types
						array( 'type' => 'string' ),
					),
				),
			),
			'required'    => array( 'config' ),
		);
		$validator = new HumanFriendlySchemaValidator( $schema );

		// Valid id object reference
		$this->assertIsValid(
			$validator->validate(
				array(
					'config' => array(
						'id' => 'test-id',
						'active' => true,
					),
				)
			)
		);

		// Valid inline object
		$this->assertIsValid(
			$validator->validate(
				array(
					'config' => array(
						'name' => 'test name',
						'values' => array( 1, 2, 3 ),
					),
				)
			)
		);

		// Valid string
		$this->assertIsValid( $validator->validate( array( 'config' => 'simple string' ) ) );

		// Invalid: object matching no branch
		$result1 = $validator->validate( array( 'config' => array( 'description' => 'no match' ) ) );
		$this->assertInstanceOf( ValidationError::class, $result1 );
		// Check for message about missing required field
		$foundMissingField = false;
		foreach ( $result1->children as $error ) {
			if ( strpos( $error->message, 'Missing required field: id' ) !== false ||
				strpos( $error->message, 'Missing required field: name' ) !== false ||
				strpos( $error->message, 'Value did not match any of the allowed shapes' ) !== false ) {
				$foundMissingField = true;
				break;
			}
		}
		$this->assertTrue( $foundMissingField, 'Missing message about missing fields or no match' );

		// Invalid: reference object missing required field
		$result2 = $validator->validate( array( 'config' => array( 'active' => true ) ) );
		$this->assertInstanceOf( ValidationError::class, $result2 );
		// Check for message about missing id field
		$foundMissingId = false;
		foreach ( $result2->children as $error ) {
			if ( strpos( $error->message, 'Missing required field: id' ) !== false ) {
				$foundMissingId = true;
				break;
			}
		}
		$this->assertTrue( $foundMissingId, 'Missing message about required id field' );
	}

	private function assertIsValid( $result, bool $shouldBeValid = true ) {
		if ( $shouldBeValid ) {
			$this->assertNull( $result );
		} else {
			$this->assertIsInvalid( $result );
		}
	}

	private function assertIsInvalid( $result ) {
		$this->assertInstanceOf( ValidationError::class, $result );
	}
}
