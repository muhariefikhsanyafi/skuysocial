import hashlib
import os
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import rollback_journal as recovery


class RecoveryJournalTests(unittest.TestCase):
    def roots(self):
        temporary = tempfile.TemporaryDirectory()
        folder = Path(temporary.name)
        managed, journal = folder / 'managed', folder / 'journal'
        managed.mkdir(mode=0o700)
        journal.mkdir(mode=0o700)
        obj = recovery.RecoveryJournal(managed, journal, 'a' * 64)
        return temporary, managed, journal, obj

    def test_explicit_recovery_only_removes_recorded_new_files_and_empty_directories(self):
        scope, managed, _, journal = self.roots()
        with scope:
            (managed / 'preexisting.txt').write_text('persistent application data')
            journal.start()
            (managed / 'new-dir').mkdir()
            journal.record('new-dir', 'directory')
            (managed / 'new-dir' / 'new.txt').write_text('created')
            journal.record('new-dir/new.txt', 'file')
            self.assertTrue((managed / 'new-dir/new.txt').exists())
            self.assertEqual({'status': 'reverted', 'objects': 2}, journal.recover())
            self.assertFalse((managed / 'new-dir').exists())
            self.assertEqual('persistent application data', (managed / 'preexisting.txt').read_text())
            with self.assertRaises(ValueError): journal.recover()

    def test_refuses_modified_or_replaced_file_without_partial_rollback(self):
        scope, managed, _, journal = self.roots()
        with scope:
            journal.start()
            (managed / 'first').write_text('fixture')
            journal.record('first', 'file')
            (managed / 'second').write_text('fixture')
            journal.record('second', 'file')
            (managed / 'first').write_text('changed by someone else')
            with self.assertRaises(ValueError): journal.recover()
            self.assertTrue((managed / 'second').exists())

    def test_refuses_symlink_swap_and_nested_escape(self):
        scope, managed, _, journal = self.roots()
        with scope, tempfile.TemporaryDirectory() as outside:
            foreign = Path(outside) / 'private'
            foreign.write_text('untouched')
            journal.start()
            (managed / 'new').write_text('created')
            journal.record('new', 'file')
            (managed / 'new').unlink()
            (managed / 'new').symlink_to(foreign)
            with self.assertRaises(ValueError): journal.recover()
            self.assertEqual('untouched', foreign.read_text())
            for path in ('../private', '/etc/passwd', 'new/../private', 'new//bad', 'new\\bad'):
                with self.assertRaises(ValueError): journal.record(path, 'file')

    def test_no_overwrite_journal_or_cross_manifest(self):
        scope, managed, private, journal = self.roots()
        with scope:
            journal.start()
            with self.assertRaises(ValueError): journal.start()
            other = recovery.RecoveryJournal(managed, private, 'b' * 64)
            with self.assertRaises(ValueError): other.recover()

    def test_recovery_refuses_unrecorded_contents_in_directory(self):
        scope, managed, _, journal = self.roots()
        with scope:
            journal.start()
            (managed / 'new-dir').mkdir()
            journal.record('new-dir', 'directory')
            (managed / 'new-dir' / 'user-content').write_text('keep')
            with self.assertRaises(OSError): journal.recover()
            self.assertTrue((managed / 'new-dir' / 'user-content').exists())

    def test_recovery_refuses_changed_metadata(self):
        scope, managed, _, journal = self.roots()
        with scope:
            journal.start()
            path = managed / 'file'
            path.write_text('fixture')
            path.chmod(0o600)
            journal.record('file', 'file')
            path.chmod(0o644)
            with self.assertRaises(ValueError): journal.recover()
            self.assertTrue(path.exists())

    def test_recovery_can_resume_after_a_complete_prior_removal(self):
        scope, managed, _, journal = self.roots()
        with scope:
            journal.start()
            (managed / 'new-dir').mkdir()
            journal.record('new-dir', 'directory')
            (managed / 'new-dir' / 'file').write_text('fixture')
            journal.record('new-dir/file', 'file')
            data = journal._read()
            data['phase'] = 'reverting'
            journal._write(data)
            (managed / 'new-dir/file').unlink()
            (managed / 'new-dir').rmdir()
            self.assertEqual('reverted', journal.recover()['status'])

    def test_recovery_refuses_hardlinked_file(self):
        scope, managed, _, journal = self.roots()
        with scope:
            journal.start()
            path = managed / 'a'
            path.write_text('fixture')
            (managed / 'b').hardlink_to(path)
            with self.assertRaises(ValueError): journal.record('a', 'file')

    def test_private_journal_permissions_required(self):
        scope, managed, private, journal = self.roots()
        with scope:
            private.chmod(0o755)
            with self.assertRaises(ValueError): recovery.RecoveryJournal(managed, private, 'a'*64)
            private.chmod(0o700)
            journal.start()
            (private / 'recovery.json').chmod(0o644)
            with self.assertRaises(ValueError): journal.recover()


if __name__ == '__main__':
    unittest.main()
