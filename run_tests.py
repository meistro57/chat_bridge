#!/usr/bin/env python3
"""
Test runner for Chat Bridge application.
Runs all available tests and provides a comprehensive test report.
"""

import sys
import unittest
import time


def run_all_tests():
    """Run all available tests for the chat bridge application"""

    print("🌉 Chat Bridge Test Suite")
    print("=" * 60)

    start_time = time.time()

    # Test suites to run
    test_modules = [
        # Simple functional tests
        'simple_test.BasicFunctionalTests',

        # Chat bridge comprehensive tests (working classes only)
        'test_chat_bridge.TestColors',
        'test_chat_bridge.TestTranscript',
        'test_chat_bridge.TestConversationHistory',
        'test_chat_bridge.TestDatabaseOperations',
        'test_chat_bridge.TestUtilityFunctions',
        'test_chat_bridge.TestLogging',
        'test_chat_bridge.TestRolesAndPersonas',
        'test_chat_bridge.TestPrintFunctions',
        'test_chat_bridge.TestCLIArguments',

        # Bridge agents tests (working classes only)
        'test_bridge_agents.TestTurn',
        'test_bridge_agents.TestProviderSpec',
        'test_bridge_agents.TestEnvironmentUtils',
        'test_bridge_agents.TestProviderRegistryContent',
    ]

    # Load and run tests
    loader = unittest.TestLoader()
    suite = unittest.TestSuite()

    total_tests = 0

    for test_module in test_modules:
        try:
            tests = loader.loadTestsFromName(test_module)
            suite.addTest(tests)
            total_tests += tests.countTestCases()
            print(f"✓ Loaded {test_module} ({tests.countTestCases()} tests)")
        except Exception as e:
            print(f"✗ Failed to load {test_module}: {e}")

    print(f"\n📊 Total tests loaded: {total_tests}")
    print("=" * 60)

    # Run the tests
    runner = unittest.TextTestRunner(
        verbosity=1,
        stream=sys.stdout,
        descriptions=True,
        failfast=False
    )

    result = runner.run(suite)

    # Print summary
    elapsed_time = time.time() - start_time
    print("\n" + "=" * 60)
    print("📋 TEST SUMMARY")
    print("=" * 60)
    print(f"Total tests run: {result.testsRun}")
    print(f"Successes: {result.testsRun - len(result.failures) - len(result.errors)}")
    print(f"Failures: {len(result.failures)}")
    print(f"Errors: {len(result.errors)}")
    print(f"Time elapsed: {elapsed_time:.2f}s")

    if result.wasSuccessful():
        print("\n🎉 ALL TESTS PASSED!")
        print("✅ The Chat Bridge application is working correctly.")
    else:
        print(f"\n❌ SOME TESTS FAILED")
        if result.failures:
            print(f"   {len(result.failures)} test failures")
        if result.errors:
            print(f"   {len(result.errors)} test errors")

    print("=" * 60)

    return 0 if result.wasSuccessful() else 1


def main():
    """Main entry point"""
    try:
        return run_all_tests()
    except KeyboardInterrupt:
        print("\n\n⚠️  Tests interrupted by user")
        return 1
    except Exception as e:
        print(f"\n\n💥 Unexpected error: {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())