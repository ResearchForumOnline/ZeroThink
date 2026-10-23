"""Local synthetic-only checks; never download or open reference/genotype files."""
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import numpy as np
import download_regional_ranges as ranges
from research_paths import require_path
from validate_regional_hwe import validate_data


class ResearchChecks(unittest.TestCase):
    def test_explicit_paths_fail_without_configuration(self):
        with patch.dict(os.environ, {}, clear=True):
            with self.assertRaisesRegex(RuntimeError, 'Set ZDNA_TEST_ROOT'):
                require_path('ZDNA_TEST_ROOT')
        with patch.dict(os.environ, {'ZDNA_TEST_ROOT': 'relative/path'}):
            with self.assertRaises(ValueError):
                require_path('ZDNA_TEST_ROOT')

    def test_hwe_constructed_cohorts_and_missing_call(self):
        doses=np.array([[0,0,0,0],[0,0,0,-1],[0,0,0,0],
                        [2,2,2,2],[2,2,2,-1],[2,2,2,2]])
        report=validate_data(doses,np.array(['SYNTHETIC_A']*3+['SYNTHETIC_B']*3))
        self.assertEqual(report['same_label_top_count'],6)
        self.assertEqual(report['minimum_callable_markers'],3)
        self.assertEqual(report['by_group']['SYNTHETIC_A']['sample_count'],3)
        self.assertIn('not consumer accuracy',report['limitations'])
        json.dumps(report,allow_nan=False)

    def test_hwe_invalid_or_unobserved_input_is_rejected(self):
        for doses,labels in [(np.zeros((2,0)),['A','B']),
                              (np.array([[0,0],[3,0],[2,2],[2,2]]),['A','A','B','B']),
                              (np.array([[-1,-1],[0,0],[2,2],[2,2]]),['A','A','B','B']),
                              (np.zeros((4,2)),['A']*4)]:
            with self.subTest(shape=doses.shape,labels=labels):
                with self.assertRaises(ValueError):validate_data(doses,labels)

    def fixture(self, root, filename='regional_rows/SYNTHETIC.bin'):
        selection=dict(retrieval_url='https://dataverse.harvard.edu/api/access/datafile/123',
                       row_bytes=4,genotype_file=dict(filesize=100,checksum=dict(value='provenance-only')),
                       samples=[dict(sample_id='SYNTHETIC_REFERENCE',row_filename=filename,byte_start=48,byte_end=51)])
        (root/'regional_selection.json').write_text(json.dumps(selection))

    def response(self,status=206,content_range='bytes 48-51/100',data=b'\x00\x01\x02\x03'):
        response=io.BytesIO(data);response.status=status;response.headers={'Content-Range':content_range}
        return response

    def test_range_retrieval_mocked_no_network(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root)
            with patch.object(ranges.urllib.request,'urlopen',return_value=self.response()) as request:
                result=ranges.run(root)
            self.assertEqual(request.call_args[0][0].get_header('Range'),'bytes=48-51')
            self.assertFalse(result['full_file_hash_verified'])
            self.assertEqual(result['rows'][0]['bytes'],4)
            self.assertEqual((root/'regional_rows/SYNTHETIC.bin').read_bytes(),b'\x00\x01\x02\x03')
            with self.assertRaises(FileExistsError):ranges.run(root)

    def test_path_escape_rejected_before_network(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);self.fixture(root,'../outside.bin')
            with patch.object(ranges.urllib.request,'urlopen') as request:
                with self.assertRaises(ValueError):ranges.run(root)
                request.assert_not_called()

    def test_mismatched_range_and_truncated_body_rejected(self):
        for response in [self.response(status=200),self.response(content_range='bytes 0-3/100'),self.response(data=b'\x00')]:
            with tempfile.TemporaryDirectory() as tmp:
                root=Path(tmp);self.fixture(root)
                with patch.object(ranges.urllib.request,'urlopen',return_value=response):
                    with self.assertRaises(ValueError):ranges.run(root)
                self.assertFalse((root/'regional_rows/SYNTHETIC.bin').exists())
                self.assertFalse((root/'regional_range_manifest.json').exists())


if __name__=='__main__':unittest.main()
